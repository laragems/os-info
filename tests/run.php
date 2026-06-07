<?php

declare(strict_types=1);

use Laragems\OsInfo\OperatingSystemInfo;
use Laragems\OsInfo\OsInfo;
use Laragems\OsInfo\Platform;
use Laragems\OsInfo\Probe\LinuxProbe;
use Laragems\OsInfo\Probe\WindowsProbe;
use Laragems\OsInfo\Support\ArchitectureNormalizer;
use Laragems\OsInfo\Value\MemoryInfo;
use Laragems\OsInfo\Value\RuntimeEnvironment;
use Laragems\OsInfo\VirtualizationType;

$autoload = __DIR__ . '/../vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run composer dump-autoload first.\n");
    exit(1);
}

require $autoload;

final class TestFailure extends RuntimeException
{
}

/**
 * @param array<string, callable(): void> $tests
 */
function runTests(array $tests): void
{
    $passed = 0;

    foreach ($tests as $name => $test) {
        try {
            $test();
            $passed++;
            fwrite(STDOUT, '.');
        } catch (Throwable $exception) {
            fwrite(STDOUT, "F\n\n");
            fwrite(STDERR, sprintf("%s failed:\n%s\n", $name, $exception->getMessage()));
            exit(1);
        }
    }

    fwrite(STDOUT, sprintf("\n%d tests passed.\n", $passed));
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure($message !== '' ? $message : sprintf(
            'Expected %s, got %s.',
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

function assertTrueValue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

runTests([
    'architecture values are normalized' => function (): void {
        assertSameValue('x86_64', ArchitectureNormalizer::normalize('amd64'));
        assertSameValue('x86_64', ArchitectureNormalizer::normalize('x86-64'));
        assertSameValue('x86', ArchitectureNormalizer::normalize('i686'));
        assertSameValue('arm64', ArchitectureNormalizer::normalize('aarch64'));
        assertSameValue('ppc64le', ArchitectureNormalizer::normalize('powerpc64le'));
        assertSameValue('unknown', ArchitectureNormalizer::normalize(''));
    },

    'platform enum exposes normalized scalar values' => function (): void {
        assertSameValue('linux', Platform::Linux->value);
        assertSameValue('windows', Platform::Windows->value);
    },

    'memory info converts kilobytes and computes usage' => function (): void {
        $memory = MemoryInfo::fromKilobytes(
            totalKilobytes: 1024,
            availableKilobytes: 256,
            freeKilobytes: 128,
            swapTotalKilobytes: 512,
            swapFreeKilobytes: 64,
        );

        assertSameValue(1048576, $memory->totalBytes());
        assertSameValue(262144, $memory->availableBytes());
        assertSameValue(786432, $memory->usedBytes());
        assertSameValue(524288, $memory->swapTotalBytes());
        assertSameValue(65536, $memory->swapFreeBytes());
    },

    'linux meminfo parser returns memory' => function (): void {
        $memory = LinuxProbe::parseMemoryInfo(<<<'MEMINFO'
MemTotal:        2048000 kB
MemFree:          512000 kB
MemAvailable:    768000 kB
SwapTotal:       1024000 kB
SwapFree:         256000 kB
MEMINFO);

        assertSameValue(2097152000, $memory->totalBytes());
        assertSameValue(786432000, $memory->availableBytes());
        assertSameValue(1310720000, $memory->usedBytes());
    },

    'linux cpuinfo parser returns cpu' => function (): void {
        $cpu = LinuxProbe::parseCpuInfo(<<<'CPUINFO'
processor   : 0
vendor_id   : GenuineIntel
cpu family  : 6
model       : 85
model name  : Example CPU 3.10GHz
cpu MHz     : 3099.998
physical id : 0
core id     : 0
cpu cores   : 2
flags       : fpu sse sse2

processor   : 1
vendor_id   : GenuineIntel
cpu family  : 6
model       : 85
model name  : Example CPU 3.10GHz
cpu MHz     : 3099.998
physical id : 0
core id     : 1
cpu cores   : 2
flags       : fpu sse sse2
CPUINFO, 'x86_64');

        assertSameValue('Example CPU 3.10GHz', $cpu->modelName());
        assertSameValue('GenuineIntel', $cpu->vendor());
        assertSameValue(2, $cpu->logicalCores());
        assertSameValue(2, $cpu->physicalCores());
        assertSameValue(['fpu', 'sse', 'sse2'], $cpu->flags());
    },

    'wmic parser handles key value output' => function (): void {
        $values = WindowsProbe::parseWmicValues("Caption=Microsoft Windows 11 Pro\r\nVersion=10.0.22631\r\n");

        assertSameValue('Microsoft Windows 11 Pro', $values['Caption']);
        assertSameValue('10.0.22631', $values['Version']);
    },

    'powershell json parser handles cim output' => function (): void {
        $values = WindowsProbe::parsePowerShellJson('{"Caption":"Microsoft Windows Server 2025","Version":"10.0.26100","FreePhysicalMemory":123456,"TotalVirtualMemorySize":456789}');

        assertSameValue('Microsoft Windows Server 2025', $values['Caption']);
        assertSameValue('10.0.26100', $values['Version']);
        assertSameValue(123456, $values['FreePhysicalMemory']);
        assertSameValue(456789, $values['TotalVirtualMemorySize']);
    },

    'runtime environment normalizes virtualization state' => function (): void {
        $runtime = new RuntimeEnvironment(
            currentUser: 'example',
            isPrivileged: false,
            shell: '/bin/sh',
            timezone: 'UTC',
            processCount: 12,
            isContainer: true,
            virtualizationType: VirtualizationType::Container,
            phpVersion: '8.5.0',
            phpSapi: 'cli',
        );

        assertSameValue('example', $runtime->currentUser());
        assertSameValue(true, $runtime->isContainer());
        assertSameValue(true, $runtime->isVirtualized());
        assertSameValue('container', $runtime->toArray()['virtualization_type']);
        assertSameValue('8.5.0', $runtime->phpVersion());
    },

    'current operating system detection returns a normalized payload' => function (): void {
        $os = OsInfo::detect();
        $payload = $os->toArray();

        assertTrueValue($os->name() !== '', 'OS name should not be empty.');
        assertTrueValue($os->architecture() !== '', 'Architecture should not be empty.');
        assertTrueValue($os->platform() instanceof Platform, 'Platform should be a Platform enum.');
        assertSameValue($payload[OperatingSystemInfo::PLATFORM], $os->platform()->value);
        assertTrueValue(!array_key_exists('type', $payload), 'Payload should not include the old type key.');
        assertTrueValue(isset($payload['memory'], $payload['cpu'], $payload['runtime'], $payload['load_average']), 'Payload should include normalized sections.');
        assertSameValue($os->isContainer(), $os->runtime()->isContainer());
        assertTrueValue(isset($payload['runtime']['php_version'], $payload['runtime']['virtualization_type']), 'Runtime payload should include PHP and virtualization fields.');
        assertTrueValue(!array_key_exists('details', $payload), 'Payload should not include a details bag.');
        assertTrueValue(json_encode($payload) !== false, 'Payload should be JSON encodable.');
    },
]);

