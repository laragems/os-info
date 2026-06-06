<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Probe;

use Laragems\OsInfo\Support\CommandRunner;
use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;

final class LinuxProbe
{
    public function __construct(
        private readonly CommandRunner $commands = new CommandRunner(),
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function release(): array
    {
        $contents = $this->readFile('/etc/os-release')
            ?? $this->readFile('/usr/lib/os-release');

        return $contents === null ? [] : self::parseKeyValue($contents);
    }

    public function memory(): MemoryInfo
    {
        $contents = $this->readFile('/proc/meminfo');

        return $contents === null ? new MemoryInfo() : self::parseMemoryInfo($contents);
    }

    public function cpu(string $architecture): CpuInfo
    {
        $contents = $this->readFile('/proc/cpuinfo');
        $cpu = $contents === null
            ? new CpuInfo($architecture)
            : self::parseCpuInfo($contents, $architecture);

        if ($cpu->logicalCores() !== null) {
            return $cpu;
        }

        $logicalCores = $this->positiveInteger($this->commands->run(['nproc']));

        return new CpuInfo(
            architecture: $architecture,
            modelName: $cpu->modelName(),
            vendor: $cpu->vendor(),
            logicalCores: $logicalCores,
            physicalCores: $cpu->physicalCores(),
            frequencyMHz: $cpu->frequencyMHz(),
            flags: $cpu->flags(),
        );
    }

    public function uptimeSeconds(): ?float
    {
        $contents = $this->readFile('/proc/uptime');

        if ($contents === null || !preg_match('/^\s*(\d+(?:\.\d+)?)/', $contents, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }

    public function isContainer(): bool
    {
        foreach (['/.dockerenv', '/run/.containerenv'] as $marker) {
            if (file_exists($marker)) {
                return true;
            }
        }

        $cgroup = $this->readFile('/proc/1/cgroup') ?? '';

        return preg_match('/docker|kubepods|containerd|libpod|lxc/i', $cgroup) === 1;
    }

    /**
     * @return array<string, string>
     */
    public static function parseKeyValue(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = stripcslashes(substr($value, 1, -1));
            }

            $values[$key] = $value;
        }

        return $values;
    }

    public static function parseMemoryInfo(string $contents): MemoryInfo
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^([A-Za-z_()]+):\s+(\d+)\s+kB\b/i', $line, $matches)) {
                $values[$matches[1]] = (int) $matches[2];
            }
        }

        return MemoryInfo::fromKilobytes(
            totalKilobytes: $values['MemTotal'] ?? null,
            availableKilobytes: $values['MemAvailable'] ?? null,
            freeKilobytes: $values['MemFree'] ?? null,
            swapTotalKilobytes: $values['SwapTotal'] ?? null,
            swapFreeKilobytes: $values['SwapFree'] ?? null,
        );
    }

    public static function parseCpuInfo(string $contents, string $architecture): CpuInfo
    {
        $blocks = self::parseCpuBlocks($contents);
        $logicalCores = self::logicalCoreCount($blocks);
        $physicalCores = self::physicalCoreCount($blocks);
        $first = $blocks[0] ?? [];

        return new CpuInfo(
            architecture: $architecture,
            modelName: self::firstExisting($first, ['model name', 'Processor', 'Hardware', 'cpu model']),
            vendor: self::firstExisting($first, ['vendor_id', 'CPU implementer', 'vendor']),
            logicalCores: $logicalCores,
            physicalCores: $physicalCores,
            frequencyMHz: self::floatValue(self::firstExisting($first, ['cpu MHz', 'clock'])),
            flags: self::flags($first),
        );
    }

    /**
     * @return list<array<string, string>>
     */
    private static function parseCpuBlocks(string $contents): array
    {
        $blocks = [];

        foreach (preg_split('/\R{2,}/', trim($contents)) ?: [] as $block) {
            $values = [];

            foreach (preg_split('/\R/', $block) ?: [] as $line) {
                if (!str_contains($line, ':')) {
                    continue;
                }

                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);

                if ($key !== '') {
                    $values[$key] = trim($value);
                }
            }

            if ($values !== []) {
                $blocks[] = $values;
            }
        }

        return $blocks;
    }

    /**
     * @param list<array<string, string>> $blocks
     */
    private static function logicalCoreCount(array $blocks): ?int
    {
        $processorCount = 0;

        foreach ($blocks as $block) {
            if (array_key_exists('processor', $block)) {
                $processorCount++;
            }
        }

        return $processorCount > 0 ? $processorCount : null;
    }

    /**
     * @param list<array<string, string>> $blocks
     */
    private static function physicalCoreCount(array $blocks): ?int
    {
        $uniqueCores = [];
        $coresBySocket = [];

        foreach ($blocks as $block) {
            if (isset($block['physical id'], $block['core id'])) {
                $uniqueCores[$block['physical id'] . ':' . $block['core id']] = true;
            }

            if (isset($block['physical id'], $block['cpu cores'])) {
                $coresBySocket[$block['physical id']] = self::integerValue($block['cpu cores']);
            }
        }

        if ($uniqueCores !== []) {
            return count($uniqueCores);
        }

        $coresBySocket = array_filter($coresBySocket, static fn (?int $value): bool => $value !== null);

        if ($coresBySocket !== []) {
            return array_sum($coresBySocket);
        }

        $first = $blocks[0] ?? [];

        return isset($first['cpu cores']) ? self::integerValue($first['cpu cores']) : null;
    }

    /**
     * @param array<string, string> $values
     * @param list<string> $keys
     */
    private static function firstExisting(array $values, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($values[$key]) && trim($values[$key]) !== '') {
                return trim($values[$key]);
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $values
     * @return list<string>
     */
    private static function flags(array $values): array
    {
        $raw = self::firstExisting($values, ['flags', 'Features']);

        if ($raw === null) {
            return [];
        }

        $flags = preg_split('/\s+/', trim($raw)) ?: [];
        $flags = array_values(array_filter($flags, static fn (string $flag): bool => $flag !== ''));

        return array_values(array_unique($flags));
    }

    private static function integerValue(?string $value): ?int
    {
        if ($value === null || !preg_match('/-?\d+/', $value, $matches)) {
            return null;
        }

        $integer = (int) $matches[0];

        return $integer > 0 ? $integer : null;
    }

    private static function floatValue(?string $value): ?float
    {
        if ($value === null || !preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
            return null;
        }

        $float = (float) $matches[0];

        return $float >= 0 ? $float : null;
    }

    private function positiveInteger(?string $value): ?int
    {
        if ($value === null || !preg_match('/^\s*(\d+)/', $value, $matches)) {
            return null;
        }

        $integer = (int) $matches[1];

        return $integer > 0 ? $integer : null;
    }

    private function readFile(string $path): ?string
    {
        if (!is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}

