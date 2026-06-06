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

    public function name(): string;

    public function platform(): Platform;

    public function version(): ?string;

    public function versionId(): ?string;

    public function architecture(): string;

    public function rawArchitecture(): string;

    public function hostname(): ?string;

    public function kernelName(): ?string;

    public function kernelRelease(): ?string;

    public function memory(): MemoryInfo;

    public function cpu(): CpuInfo;

    public function runtime(): RuntimeEnvironment;

    public function uptimeSeconds(): ?float;

    /**
     * @return array{1m: ?float, 5m: ?float, 15m: ?float}
     */
    public function loadAverage(): array;

    public function isContainer(): bool;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

