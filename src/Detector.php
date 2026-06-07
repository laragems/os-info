<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

use Laragems\OsInfo\Probe\LinuxProbe;
use Laragems\OsInfo\Probe\MacOsProbe;
use Laragems\OsInfo\Probe\WindowsProbe;
use Laragems\OsInfo\Support\ArchitectureNormalizer;
use Laragems\OsInfo\Support\CommandRunner;
use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;
use Laragems\OsInfo\Value\RuntimeEnvironment;

final class Detector
{
    /**
     * Creates a detector with an optional command runner.
     */
    public function __construct(
        private readonly ?CommandRunner $commands = null,
    ) {
    }

    /**
     * Detects operating system information for the current process.
     */
    public function detect(): OperatingSystemInfo
    {
        $commands = $this->commands ?? new CommandRunner();
        $platform = Platform::fromPhpOsFamily(PHP_OS_FAMILY);
        $rawArchitecture = $this->firstNonEmpty(@php_uname('m'), 'unknown');
        $architecture = ArchitectureNormalizer::normalize($rawArchitecture);

        $profile = match ($platform) {
            Platform::Linux => $this->detectLinux($commands, $architecture),
            Platform::MacOS => $this->detectMacOs($commands, $architecture),
            Platform::Windows => $this->detectWindows($commands, $architecture),
            default => $this->detectFallback($architecture),
        };
        $runtimeDetector = new RuntimeEnvironmentDetector($commands);
        $isContainer = $profile['is_container'];

        return new DetectedOperatingSystemInfo(
            name: $profile['name'],
            platform: $platform,
            version: $profile['version'],
            versionId: $profile['version_id'],
            architecture: $architecture,
            rawArchitecture: $rawArchitecture,
            hostname: $this->nullable(gethostname() ?: null),
            kernelName: $this->nullable(@php_uname('s')),
            kernelRelease: $this->nullable(@php_uname('r')),
            memory: $profile['memory'],
            cpu: $profile['cpu'],
            runtime: static fn (): RuntimeEnvironment => $runtimeDetector->detect($platform, $isContainer),
            uptimeSeconds: $profile['uptime_seconds'],
            loadAverage: $this->loadAverage(),
        );
    }

    /**
     * Detects Linux-specific operating system details.
     *
     * @return array{
     *     name: string,
     *     version: ?string,
     *     version_id: ?string,
     *     memory: MemoryInfo,
     *     cpu: CpuInfo,
     *     uptime_seconds: ?float,
     *     is_container: bool
     * }
     */
    private function detectLinux(CommandRunner $commands, string $architecture): array
    {
        $probe = new LinuxProbe($commands);
        $release = $probe->release();

        return [
            'name' => $this->firstNonEmpty($release['NAME'] ?? null, $release['PRETTY_NAME'] ?? null, 'Linux'),
            'version' => $this->nullable($release['VERSION'] ?? $release['VERSION_ID'] ?? null),
            'version_id' => $this->nullable($release['VERSION_ID'] ?? null),
            'memory' => $probe->memory(),
            'cpu' => $probe->cpu($architecture),
            'uptime_seconds' => $probe->uptimeSeconds(),
            'is_container' => $probe->isContainer(),
        ];
    }

    /**
     * Detects macOS-specific operating system details.
     *
     * @return array{
     *     name: string,
     *     version: ?string,
     *     version_id: ?string,
     *     memory: MemoryInfo,
     *     cpu: CpuInfo,
     *     uptime_seconds: ?float,
     *     is_container: bool
     * }
     */
    private function detectMacOs(CommandRunner $commands, string $architecture): array
    {
        $probe = new MacOsProbe($commands);
        $version = $probe->version();

        return [
            'name' => 'macOS',
            'version' => $version,
            'version_id' => $version,
            'memory' => $probe->memory(),
            'cpu' => $probe->cpu($architecture),
            'uptime_seconds' => $probe->uptimeSeconds(),
            'is_container' => false,
        ];
    }

    /**
     * Detects Windows-specific operating system details.
     *
     * @return array{
     *     name: string,
     *     version: ?string,
     *     version_id: ?string,
     *     memory: MemoryInfo,
     *     cpu: CpuInfo,
     *     uptime_seconds: ?float,
     *     is_container: bool
     * }
     */
    private function detectWindows(CommandRunner $commands, string $architecture): array
    {
        $probe = new WindowsProbe($commands);
        $os = $probe->operatingSystem();

        return [
            'name' => $this->firstNonEmpty($os['Caption'] ?? null, @php_uname('s'), 'Windows'),
            'version' => $this->nullable($os['Version'] ?? null),
            'version_id' => $this->nullable($os['Version'] ?? null),
            'memory' => $probe->memory(),
            'cpu' => $probe->cpu($architecture),
            'uptime_seconds' => $probe->uptimeSeconds(),
            'is_container' => false,
        ];
    }

    /**
     * Builds a fallback profile for unknown platforms.
     *
     * @return array{
     *     name: string,
     *     version: ?string,
     *     version_id: ?string,
     *     memory: MemoryInfo,
     *     cpu: CpuInfo,
     *     uptime_seconds: ?float,
     *     is_container: bool
     * }
     */
    private function detectFallback(string $architecture): array
    {
        return [
            'name' => $this->firstNonEmpty(@php_uname('s'), PHP_OS_FAMILY, 'Unknown'),
            'version' => $this->nullable(@php_uname('r')),
            'version_id' => null,
            'memory' => new MemoryInfo(),
            'cpu' => new CpuInfo($architecture),
            'uptime_seconds' => null,
            'is_container' => false,
        ];
    }

    /**
     * Returns the system load average values when available.
     *
     * @return array{1m: ?float, 5m: ?float, 15m: ?float}
     */
    private function loadAverage(): array
    {
        if (!function_exists('sys_getloadavg')) {
            return ['1m' => null, '5m' => null, '15m' => null];
        }

        $load = @sys_getloadavg();

        if ($load === false) {
            return ['1m' => null, '5m' => null, '15m' => null];
        }

        return [
            '1m' => isset($load[0]) ? (float) $load[0] : null,
            '5m' => isset($load[1]) ? (float) $load[1] : null,
            '15m' => isset($load[2]) ? (float) $load[2] : null,
        ];
    }

    /**
     * Returns the first non-empty string from the provided values.
     */
    private function firstNonEmpty(?string ...$values): string
    {
        foreach ($values as $value) {
            $value = $this->nullable($value);

            if ($value !== null) {
                return $value;
            }
        }

        return 'unknown';
    }

    /**
     * Trims a string and converts empty values to null.
     */
    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

