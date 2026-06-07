<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

use Laragems\OsInfo\Value\CpuInfo;
use Laragems\OsInfo\Value\MemoryInfo;
use Laragems\OsInfo\Value\RuntimeEnvironment;

interface OperatingSystemInfo
{
    public const NAME = 'name';
    public const PLATFORM = 'platform';
    public const VERSION = 'version';
    public const VERSION_ID = 'version_id';
    public const ARCHITECTURE = 'architecture';
    public const RAW_ARCHITECTURE = 'raw_architecture';
    public const HOSTNAME = 'hostname';
    public const KERNEL_NAME = 'kernel_name';
    public const KERNEL_RELEASE = 'kernel_release';
    public const MEMORY = 'memory';
    public const CPU = 'cpu';
    public const RUNTIME = 'runtime';
    public const UPTIME_SECONDS = 'uptime_seconds';
    public const LOAD_AVERAGE = 'load_average';
    public const IS_CONTAINER = 'is_container';

    /**
     * Returns the operating system display name.
     */
    public function name(): string;

    /**
     * Returns the normalized platform family.
     */
    public function platform(): Platform;

    /**
     * Returns the operating system version label.
     */
    public function version(): ?string;

    /**
     * Returns the machine-readable operating system version ID.
     */
    public function versionId(): ?string;

    /**
     * Returns the normalized CPU/system architecture.
     */
    public function architecture(): string;

    /**
     * Returns the raw architecture reported by the system.
     */
    public function rawArchitecture(): string;

    /**
     * Returns the current host name.
     */
    public function hostname(): ?string;

    /**
     * Returns the kernel name.
     */
    public function kernelName(): ?string;

    /**
     * Returns the kernel release string.
     */
    public function kernelRelease(): ?string;

    /**
     * Returns a memory information snapshot.
     */
    public function memory(): MemoryInfo;

    /**
     * Returns CPU information.
     */
    public function cpu(): CpuInfo;

    /**
     * Returns runtime environment information.
     */
    public function runtime(): RuntimeEnvironment;

    /**
     * Returns system uptime in seconds.
     */
    public function uptimeSeconds(): ?float;

    /**
     * Returns the one, five, and fifteen minute load averages.
     *
     * @return array{1m: ?float, 5m: ?float, 15m: ?float}
     */
    public function loadAverage(): array;

    /**
     * Returns whether the runtime appears to be in a container.
     */
    public function isContainer(): bool;

    /**
     * Returns all available information as an array payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

