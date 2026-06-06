<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Probe;

use Laragems\OsInfo\Support\CommandRunner;
use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;

final class MacOsProbe
{
    public function __construct(
        private readonly CommandRunner $commands = new CommandRunner(),
    ) {
    }

    public function version(): ?string
    {
        return $this->commands->run(['sw_vers', '-productVersion']);
    }

    public function memory(): MemoryInfo
    {
        $total = $this->positiveInteger($this->commands->run(['sysctl', '-n', 'hw.memsize']));

        return new MemoryInfo(
            totalBytes: $total,
            availableBytes: $this->availableMemoryBytes(),
        );
    }

    public function cpu(string $architecture): CpuInfo
    {
        $frequency = $this->positiveInteger($this->commands->run(['sysctl', '-n', 'hw.cpufrequency']));

        return new CpuInfo(
            architecture: $architecture,
            modelName: $this->commands->run(['sysctl', '-n', 'machdep.cpu.brand_string']),
            vendor: $this->commands->run(['sysctl', '-n', 'machdep.cpu.vendor']),
            logicalCores: $this->positiveInteger($this->commands->run(['sysctl', '-n', 'hw.logicalcpu'])),
            physicalCores: $this->positiveInteger($this->commands->run(['sysctl', '-n', 'hw.physicalcpu'])),
            frequencyMHz: $frequency === null ? null : $frequency / 1000000,
        );
    }

    public function uptimeSeconds(): ?float
    {
        $bootTime = $this->commands->run(['sysctl', '-n', 'kern.boottime']);

        if ($bootTime === null || !preg_match('/sec\s*=\s*(\d+)/', $bootTime, $matches)) {
            return null;
        }

        return max(0, time() - (int) $matches[1]);
    }

    private function availableMemoryBytes(): ?int
    {
        $vmStat = $this->commands->run(['vm_stat']);

        if ($vmStat === null || !preg_match('/page size of (\d+) bytes/i', $vmStat, $sizeMatch)) {
            return null;
        }

        $pageSize = (int) $sizeMatch[1];
        $pages = 0;

        foreach (['Pages free', 'Pages inactive', 'Pages speculative'] as $label) {
            if (preg_match('/' . preg_quote($label, '/') . ':\s+([\d.]+)/i', $vmStat, $matches)) {
                $pages += (int) str_replace('.', '', $matches[1]);
            }
        }

        return $pages > 0 ? $pages * $pageSize : null;
    }

    private function positiveInteger(?string $value): ?int
    {
        if ($value === null || !preg_match('/^\s*(\d+)/', $value, $matches)) {
            return null;
        }

        $integer = (int) $matches[1];

        return $integer > 0 ? $integer : null;
    }
}

