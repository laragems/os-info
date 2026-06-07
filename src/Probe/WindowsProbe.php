<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Probe;

use Laragems\OsInfo\Support\CommandRunner;
use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;

final class WindowsProbe
{
    /**
     * Creates a Windows probe with an optional command runner.
     */
    public function __construct(
        private readonly CommandRunner $commands = new CommandRunner(),
    ) {
    }

    /**
     * Returns Windows operating system metadata.
     *
     * @return array<string, mixed>
     */
    public function operatingSystem(): array
    {
        $output = $this->commands->run([
            'wmic',
            'os',
            'get',
            'Caption,Version,FreePhysicalMemory,FreeVirtualMemory,TotalVisibleMemorySize,TotalVirtualMemorySize',
            '/Value',
        ]);

        $values = $output === null ? [] : self::parseWmicValues($output);

        if ($values !== []) {
            return $values;
        }

        return $this->powershellObject(
            'Get-CimInstance Win32_OperatingSystem | Select-Object Caption,Version,FreePhysicalMemory,FreeVirtualMemory,TotalVisibleMemorySize,TotalVirtualMemorySize | ConvertTo-Json -Compress'
        );
    }

    /**
     * Returns a Windows memory information snapshot.
     */
    public function memory(): MemoryInfo
    {
        $os = $this->operatingSystem();
        $totalKilobytes = $this->positiveInteger($os['TotalVisibleMemorySize'] ?? null);
        $availableKilobytes = $this->positiveInteger($os['FreePhysicalMemory'] ?? null);
        $totalVirtualKilobytes = $this->positiveInteger($os['TotalVirtualMemorySize'] ?? null);
        $freeVirtualKilobytes = $this->positiveInteger($os['FreeVirtualMemory'] ?? null);

        return MemoryInfo::fromKilobytes(
            totalKilobytes: $totalKilobytes,
            availableKilobytes: $availableKilobytes,
            freeKilobytes: $availableKilobytes,
            swapTotalKilobytes: $this->difference($totalVirtualKilobytes, $totalKilobytes),
            swapFreeKilobytes: $this->difference($freeVirtualKilobytes, $availableKilobytes),
        );
    }

    /**
     * Returns Windows uptime in seconds.
     */
    public function uptimeSeconds(): ?float
    {
        $output = $this->commands->run([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            '$os = Get-CimInstance Win32_OperatingSystem; [Math]::Round(((Get-Date) - $os.LastBootUpTime).TotalSeconds, 2)',
        ], 10.0);

        return $this->positiveFloat($output);
    }

    /**
     * Returns Windows CPU information.
     */
    public function cpu(string $architecture): CpuInfo
    {
        $output = $this->commands->run([
            'wmic',
            'cpu',
            'get',
            'Manufacturer,MaxClockSpeed,Name,NumberOfCores,NumberOfLogicalProcessors',
            '/Value',
        ]);
        $cpu = $output === null ? [] : self::parseWmicValues($output);

        if ($cpu === []) {
            $cpu = $this->powershellObject(
                'Get-CimInstance Win32_Processor | Select-Object -First 1 Manufacturer,MaxClockSpeed,Name,NumberOfCores,NumberOfLogicalProcessors | ConvertTo-Json -Compress'
            );
        }

        return new CpuInfo(
            architecture: $architecture,
            modelName: $this->nullable($cpu['Name'] ?? null),
            vendor: $this->nullable($cpu['Manufacturer'] ?? null),
            logicalCores: $this->positiveInteger($cpu['NumberOfLogicalProcessors'] ?? null),
            physicalCores: $this->positiveInteger($cpu['NumberOfCores'] ?? null),
            frequencyMHz: $this->positiveFloat($cpu['MaxClockSpeed'] ?? null),
        );
    }

    /**
     * Parses WMIC key-value output.
     *
     * @return array<string, string>
     */
    public static function parseWmicValues(string $output): array
    {
        $values = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if ($key !== '') {
                $values[$key] = trim($value);
            }
        }

        return $values;
    }

    /**
     * Parses PowerShell JSON object output.
     *
     * @return array<string, mixed>
     */
    public static function parsePowerShellJson(string $output): array
    {
        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            $decoded = $decoded[0] ?? [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $values = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Runs a PowerShell command and parses JSON output.
     *
     * @return array<string, mixed>
     */
    private function powershellObject(string $command): array
    {
        $output = $this->commands->run([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $command,
        ], 10.0);

        return $output === null ? [] : self::parsePowerShellJson($output);
    }

    /**
     * Converts scalar values into trimmed nullable strings.
     */
    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Parses a positive integer from a scalar value.
     */
    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $integer = (int) $value;

            return $integer > 0 ? $integer : null;
        }

        if (!is_string($value) || !preg_match('/^\s*(\d+)/', $value, $matches)) {
            return null;
        }

        $integer = (int) $matches[1];

        return $integer > 0 ? $integer : null;
    }

    /**
     * Parses a non-negative float from a scalar value.
     */
    private function positiveFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $float = (float) $value;

            return $float >= 0 ? $float : null;
        }

        if (!is_string($value) || !preg_match('/^\s*(\d+(?:\.\d+)?)/', $value, $matches)) {
            return null;
        }

        $float = (float) $matches[1];

        return $float >= 0 ? $float : null;
    }

    /**
     * Returns a non-negative difference when both values exist.
     */
    private function difference(?int $left, ?int $right): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }

        return max(0, $left - $right);
    }
}

