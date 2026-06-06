<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

use Laragems\OsInfo\Support\CommandRunner;
use Laragems\OsInfo\Value\RuntimeEnvironment;

final class RuntimeEnvironmentDetector
{
    public function __construct(
        private readonly CommandRunner $commands = new CommandRunner(),
    ) {
    }

    public function detect(Platform $platform, bool $isContainer): RuntimeEnvironment
    {
        $isContainer = $isContainer || $this->detectContainer($platform);
        $virtualizationType = $this->virtualizationType($platform, $isContainer);

        return new RuntimeEnvironment(
            currentUser: $this->currentUser($platform),
            isPrivileged: $this->isPrivileged($platform),
            shell: $this->shell($platform),
            timezone: $this->timezone($platform),
            processCount: $this->processCount($platform),
            isContainer: $isContainer,
            virtualizationType: $virtualizationType,
        );
    }

    private function currentUser(Platform $platform): ?string
    {
        if ($platform === Platform::Windows) {
            $domain = $this->nullable(getenv('USERDOMAIN') ?: null);
            $username = $this->nullable(getenv('USERNAME') ?: null);

            if ($domain !== null && $username !== null) {
                return $domain . '\\' . $username;
            }
        }

        return $this->firstNonEmpty(
            getenv('USER') ?: null,
            getenv('USERNAME') ?: null,
            $this->commands->run(['whoami'])
        );
    }

    private function isPrivileged(Platform $platform): ?bool
    {
        if ($platform === Platform::Windows) {
            $output = $this->commands->run([
                'powershell',
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                '[Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)',
            ], 10.0);

            return $this->boolean($output);
        }

        if (function_exists('posix_geteuid')) {
            return posix_geteuid() === 0;
        }

        $uid = $this->positiveInteger($this->commands->run(['id', '-u']));

        return $uid === null ? null : $uid === 0;
    }

    private function shell(Platform $platform): ?string
    {
        if ($platform === Platform::Windows) {
            return $this->firstNonEmpty(
                getenv('ComSpec') ?: null,
                getenv('SHELL') ?: null
            );
        }

        return $this->firstNonEmpty(
            getenv('SHELL') ?: null,
            $this->commands->run(['sh', '-c', 'printf "%s" "$SHELL"'])
        );
    }

    private function timezone(Platform $platform): ?string
    {
        $systemTimezone = match ($platform) {
            Platform::Linux => $this->linuxTimezone(),
            Platform::MacOS, Platform::BSD => $this->unixLocaltimeZone(),
            Platform::Windows => $this->commands->run([
                'powershell',
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                '(Get-TimeZone).Id',
            ], 10.0),
            default => null,
        };

        return $this->firstNonEmpty($systemTimezone, date_default_timezone_get());
    }

    private function processCount(Platform $platform): ?int
    {
        if ($platform === Platform::Linux) {
            $fromProc = $this->linuxProcessCount();

            if ($fromProc !== null) {
                return $fromProc;
            }
        }

        if ($platform === Platform::Windows) {
            $count = $this->positiveInteger($this->commands->run([
                'powershell',
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                '(Get-Process).Count',
            ], 10.0));

            if ($count !== null) {
                return $count;
            }
        }

        $output = $this->commands->run(['ps', '-e', '-o', 'pid=']);

        if ($output === null) {
            $output = $this->commands->run(['ps', '-ax', '-o', 'pid=']);
        }

        if ($output === null) {
            return null;
        }

        $lines = array_filter(
            preg_split('/\R/', trim($output)) ?: [],
            static fn (string $line): bool => trim($line) !== ''
        );

        return count($lines);
    }

    private function detectContainer(Platform $platform): bool
    {
        if ($platform === Platform::Windows) {
            $username = strtolower($this->nullable(getenv('USERNAME') ?: null) ?? '');

            if (in_array($username, ['containeradministrator', 'containeruser'], true)) {
                return true;
            }

            $containerType = $this->commands->run([
                'powershell',
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-Command',
                '(Get-ItemProperty "HKLM:\SYSTEM\CurrentControlSet\Control" -Name ContainerType -ErrorAction SilentlyContinue).ContainerType',
            ], 10.0);

            return $this->nullable($containerType) !== null;
        }

        return false;
    }

    private function virtualizationType(Platform $platform, bool $isContainer): VirtualizationType
    {
        if ($isContainer) {
            return VirtualizationType::Container;
        }

        return match ($platform) {
            Platform::Linux => $this->linuxVirtualizationType(),
            Platform::MacOS => $this->macOsVirtualizationType(),
            Platform::Windows => $this->windowsVirtualizationType(),
            default => VirtualizationType::Unknown,
        };
    }

    private function linuxVirtualizationType(): VirtualizationType
    {
        $release = $this->readFile('/proc/sys/kernel/osrelease') ?? '';
        $version = $this->readFile('/proc/version') ?? '';

        if (preg_match('/microsoft|wsl/i', $release . "\n" . $version) === 1) {
            return VirtualizationType::Subsystem;
        }

        foreach ([
            '/sys/class/dmi/id/product_name',
            '/sys/class/dmi/id/sys_vendor',
        ] as $path) {
            $value = $this->readFile($path);

            if ($value !== null && preg_match('/virtual|vmware|kvm|qemu|hyper-v|xen|virtualbox|parallels/i', $value) === 1) {
                return VirtualizationType::VirtualMachine;
            }
        }

        return VirtualizationType::Unknown;
    }

    private function macOsVirtualizationType(): VirtualizationType
    {
        $hypervisor = $this->commands->run(['sysctl', '-n', 'kern.hv_vmm_present']);

        if ($this->nullable($hypervisor) === '1') {
            return VirtualizationType::VirtualMachine;
        }

        return VirtualizationType::Unknown;
    }

    private function windowsVirtualizationType(): VirtualizationType
    {
        $model = $this->commands->run([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            '(Get-CimInstance Win32_ComputerSystem).Model',
        ], 10.0);

        if ($model !== null && preg_match('/virtual|vmware|kvm|qemu|hyper-v|xen|virtualbox|parallels/i', $model) === 1) {
            return VirtualizationType::VirtualMachine;
        }

        return VirtualizationType::Unknown;
    }

    private function linuxTimezone(): ?string
    {
        $timezone = $this->readFile('/etc/timezone');

        if ($this->nullable($timezone) !== null) {
            return $this->nullable($timezone);
        }

        return $this->unixLocaltimeZone();
    }

    private function unixLocaltimeZone(): ?string
    {
        $target = @readlink('/etc/localtime');

        if ($target === false) {
            return null;
        }

        $marker = 'zoneinfo/';
        $position = strpos($target, $marker);

        if ($position === false) {
            return null;
        }

        return substr($target, $position + strlen($marker));
    }

    private function linuxProcessCount(): ?int
    {
        if (!is_dir('/proc')) {
            return null;
        }

        $entries = @scandir('/proc');

        if ($entries === false) {
            return null;
        }

        $count = 0;

        foreach ($entries as $entry) {
            if (ctype_digit($entry)) {
                $count++;
            }
        }

        return $count;
    }

    private function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $value = $this->nullable($value);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function boolean(?string $value): ?bool
    {
        $value = $this->nullable($value);

        if ($value === null) {
            return null;
        }

        return match (strtolower($value)) {
            'true', '1', 'yes' => true,
            'false', '0', 'no' => false,
            default => null,
        };
    }

    private function positiveInteger(?string $value): ?int
    {
        if ($value === null || !preg_match('/^\s*(\d+)/', $value, $matches)) {
            return null;
        }

        $integer = (int) $matches[1];

        return $integer >= 0 ? $integer : null;
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

