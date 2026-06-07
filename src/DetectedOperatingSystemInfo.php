<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;
use Laragems\OsInfo\Value\RuntimeEnvironment;

final class DetectedOperatingSystemInfo implements OperatingSystemInfo
{
    /**
     * Creates an operating system information result.
     *
     * @param RuntimeEnvironment|\Closure $runtime Runtime value or resolver returning RuntimeEnvironment.
     * @phpstan-param RuntimeEnvironment|\Closure(): RuntimeEnvironment $runtime
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
        private readonly RuntimeEnvironment|\Closure $runtime,
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

    /**
     * Returns the operating system display name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the normalized platform family.
     */
    public function platform(): Platform
    {
        return $this->platform;
    }

    /**
     * Returns the operating system version label.
     */
    public function version(): ?string
    {
        return $this->version;
    }

    /**
     * Returns the machine-readable operating system version ID.
     */
    public function versionId(): ?string
    {
        return $this->versionId;
    }

    /**
     * Returns the normalized CPU/system architecture.
     */
    public function architecture(): string
    {
        return $this->architecture;
    }

    /**
     * Returns the raw architecture reported by the system.
     */
    public function rawArchitecture(): string
    {
        return $this->rawArchitecture;
    }

    /**
     * Returns the current host name.
     */
    public function hostname(): ?string
    {
        return $this->hostname;
    }

    /**
     * Returns the kernel name.
     */
    public function kernelName(): ?string
    {
        return $this->kernelName;
    }

    /**
     * Returns the kernel release string.
     */
    public function kernelRelease(): ?string
    {
        return $this->kernelRelease;
    }

    /**
     * Returns a memory information snapshot.
     */
    public function memory(): MemoryInfo
    {
        return $this->memory;
    }

    /**
     * Returns CPU information.
     */
    public function cpu(): CpuInfo
    {
        return $this->cpu;
    }

    /**
     * Returns runtime environment information.
     */
    public function runtime(): RuntimeEnvironment
    {
        if ($this->runtime instanceof RuntimeEnvironment) {
            return $this->runtime;
        }

        return ($this->runtime)();
    }

    /**
     * Returns system uptime in seconds.
     */
    public function uptimeSeconds(): ?float
    {
        return $this->uptimeSeconds;
    }

    /**
     * Returns the one, five, and fifteen minute load averages.
     *
     * @return array{1m: ?float, 5m: ?float, 15m: ?float}
     */
    public function loadAverage(): array
    {
        return $this->loadAverage;
    }

    /**
     * Returns whether the runtime appears to be in a container.
     */
    public function isContainer(): bool
    {
        return $this->runtime()->isContainer();
    }

    /**
     * Returns all available information as an array payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $runtime = $this->runtime();

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
            self::RUNTIME => $runtime->toArray(),
            self::UPTIME_SECONDS => $this->uptimeSeconds(),
            self::LOAD_AVERAGE => $this->loadAverage(),
            self::IS_CONTAINER => $runtime->isContainer(),
        ];
    }
}

