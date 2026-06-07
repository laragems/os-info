<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Probe;

use Laragems\OsInfo\Support\CommandRunner;
use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;

final class LinuxProbe
{
    /**
     * Creates a Linux probe with an optional command runner.
     */
    public function __construct(
        private readonly CommandRunner $commands = new CommandRunner(),
    ) {
    }

    /**
     * Returns parsed Linux release metadata.
     *
     * @return array<string, string>
     */
    public function release(): array
    {
        $contents = $this->readFile('/etc/os-release')
            ?? $this->readFile('/usr/lib/os-release');

        return $contents === null ? [] : self::parseKeyValue($contents);
    }

    /**
     * Returns a Linux memory information snapshot.
     */
    public function memory(): MemoryInfo
    {
        $contents = $this->readFile('/proc/meminfo');

        return $contents === null ? new MemoryInfo() : self::parseMemoryInfo($contents);
    }

    /**
     * Returns Linux CPU information.
     */
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

    /**
     * Returns Linux uptime in seconds.
     */
    public function uptimeSeconds(): ?float
    {
        $contents = $this->readFile('/proc/uptime');

        if ($contents === null || !preg_match('/^\s*(\d+(?:\.\d+)?)/', $contents, $matches)) {
            return null;
        }

        return (float) $matches[1];
    }

    /**
     * Returns whether Linux container markers are present.
     */
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
     * Parses shell-style key-value metadata.
     *
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

    /**
     * Parses /proc/meminfo into memory information.
     */
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

    /**
     * Parses /proc/cpuinfo into CPU information.
     */
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
     * Splits /proc/cpuinfo into CPU value blocks.
     *
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
     * Counts logical CPU cores from parsed CPU blocks.
     *
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
     * Counts physical CPU cores from parsed CPU blocks.
     *
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
     * Returns the first existing non-empty value for a key list.
     *
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
     * Parses CPU feature flags from a CPU value block.
     *
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

    /**
     * Parses a positive integer from a string.
     */
    private static function integerValue(?string $value): ?int
    {
        if ($value === null || !preg_match('/-?\d+/', $value, $matches)) {
            return null;
        }

        $integer = (int) $matches[0];

        return $integer > 0 ? $integer : null;
    }

    /**
     * Parses a non-negative float from a string.
     */
    private static function floatValue(?string $value): ?float
    {
        if ($value === null || !preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
            return null;
        }

        $float = (float) $matches[0];

        return $float >= 0 ? $float : null;
    }

    /**
     * Parses a positive integer from command output.
     */
    private function positiveInteger(?string $value): ?int
    {
        if ($value === null || !preg_match('/^\s*(\d+)/', $value, $matches)) {
            return null;
        }

        $integer = (int) $matches[1];

        return $integer > 0 ? $integer : null;
    }

    /**
     * Reads a file when it is available and readable.
     */
    private function readFile(string $path): ?string
    {
        if (!is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}

