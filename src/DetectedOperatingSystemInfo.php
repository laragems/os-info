<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;
use Laragems\OsInfo\Value\RuntimeEnvironment;

final class DetectedOperatingSystemInfo implements OperatingSystemInfo
{
    /**
     * @param array{1m: ?float, 5m: ?float, 15m: ?float} $loadAverage
     */
    public function __construct(
        private readonly string $name,
        private readonly Platform $platform,
        private readonly ?string $version,
        private readonly ?string $versionId,
        private readonly string $architecture,
        private readonly string $rawArchitecture,
        private readonly ?string $hostname,
        private readonly ?string $kernelName,
        private readonly ?string $kernelRelease,
        private readonly MemoryInfo $memory,
        private readonly CpuInfo $cpu,
        private readonly RuntimeEnvironment $runtime,
        private readonly ?float $uptimeSeconds,
        private readonly array $loadAverage,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('The operating system name cannot be empty.');
        }

        if ($this->architecture === '') {
            throw new \InvalidArgumentException('The normalized architecture cannot be empty.');
        }

        if ($this->rawArchitecture === '') {
            throw new \InvalidArgumentException('The raw architecture cannot be empty.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function versionId(): ?string
    {
        return $this->versionId;
    }

    public function architecture(): string
    {
        return $this->architecture;
    }

    public function rawArchitecture(): string
    {
        return $this->rawArchitecture;
    }

    public function hostname(): ?string
    {
        return $this->hostname;
    }

    public function kernelName(): ?string
    {
        return $this->kernelName;
    }

    public function kernelRelease(): ?string
    {
        return $this->kernelRelease;
    }

    public function memory(): MemoryInfo
    {
        return $this->memory;
    }

    public function cpu(): CpuInfo
    {
        return $this->cpu;
    }

    public function runtime(): RuntimeEnvironment
    {
        return $this->runtime;
    }

    public function uptimeSeconds(): ?float
    {
        return $this->uptimeSeconds;
    }

    public function loadAverage(): array
    {
        return $this->loadAverage;
    }

    public function isContainer(): bool
    {
        return $this->runtime()->isContainer();
    }

    public function toArray(): array
    {
        return [
            self::NAME => $this->name(),
            self::PLATFORM => $this->platform()->value,
            self::VERSION => $this->version(),
            self::VERSION_ID => $this->versionId(),
            self::ARCHITECTURE => $this->architecture(),
            self::RAW_ARCHITECTURE => $this->rawArchitecture(),
            self::HOSTNAME => $this->hostname(),
            self::KERNEL_NAME => $this->kernelName(),
            self::KERNEL_RELEASE => $this->kernelRelease(),
            self::MEMORY => $this->memory()->toArray(),
            self::CPU => $this->cpu()->toArray(),
            self::RUNTIME => $this->runtime()->toArray(),
            self::UPTIME_SECONDS => $this->uptimeSeconds(),
            self::LOAD_AVERAGE => $this->loadAverage(),
            self::IS_CONTAINER => $this->isContainer(),
        ];
    }
}

