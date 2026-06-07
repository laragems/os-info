# Laragems OS Info

`laragems/os-info` provides unified OS, CPU, memory, and runtime environment information for PHP applications.
It is framework-free and has no third-party runtime dependencies.

## Installation

```bash
composer require laragems/os-info
```

## Quick Start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Laragems\OsInfo\OsInfo;
use Laragems\OsInfo\Platform;

$info = OsInfo::detect();

echo $info->name();              // Ubuntu, macOS, Microsoft Windows 11 Pro
echo $info->platform()->value;   // linux, macos, windows
echo $info->architecture();      // x86_64, arm64, x86
echo $info->memory()->totalBytes();
echo $info->cpu()->logicalCores();
echo $info->runtime()->currentUser();

if ($info->platform() === Platform::Linux) {
    echo 'Running on Linux';
}
```

## API Usage

The detector returns an `OperatingSystemInfo` object with typed value objects for
memory, CPU, and runtime environment:

```php
<?php

use Laragems\OsInfo\OsInfo;

$os = OsInfo::detect();

printf(
    "%s %s on %s\n",
    $os->name(),
    $os->versionId() ?? 'unknown',
    $os->architecture(),
);

printf(
    "%d logical cores, %s RAM\n",
    $os->cpu()->logicalCores() ?? 0,
    number_format(($os->memory()->totalBytes() ?? 0) / 1024 / 1024 / 1024, 2) . ' GiB',
);

echo $os->runtime()->virtualizationType()->value;
```

Example output from a WSL machine:

```text
Ubuntu 26.04 on x86_64
20 logical cores, 7.65 GiB RAM
subsystem
```

You can also work with a plain array payload:

```php
<?php

use Laragems\OsInfo\OperatingSystemInfo;
use Laragems\OsInfo\OsInfo;

$payload = OsInfo::detect()->toArray();

echo $payload[OperatingSystemInfo::NAME];       // Ubuntu
echo $payload[OperatingSystemInfo::PLATFORM];   // linux
echo $payload[OperatingSystemInfo::CPU]['model_name'];
echo json_encode($payload, JSON_PRETTY_PRINT);
```

## Use Without Composer

Download or copy this package, then require its bundled autoloader:

```php
<?php

require __DIR__ . '/os-info/src/autoload.php';

use Laragems\OsInfo\OsInfo;
use Laragems\OsInfo\OperatingSystemInfo;

$info = OsInfo::detect();
$payload = $info->toArray();

echo $payload[OperatingSystemInfo::PLATFORM];
```

## CLI

Composer install:

```bash
vendor/bin/os-info
vendor/bin/os-info --json
```

No Composer:

```bash
php bin/os-info
php bin/os-info --json
```

Example output captured from WSL:

```text
Operating System
  Name:              Ubuntu
  Platform:          linux
  Version:           26.04 (Resolute Raccoon)
  Version ID:        26.04
  Architecture:      x86_64
  Raw Architecture:  x86_64
  Hostname:          legion
  Kernel:            Linux 6.6.87.2-microsoft-standard-WSL2
  Container:         no
  Uptime:            12h 46m

CPU
  Model:             12th Gen Intel(R) Core(TM) i7-12700H
  Vendor:            GenuineIntel
  Architecture:      x86_64
  Logical Cores:     20
  Physical Cores:    10
  Frequency:         2688.00 MHz
  Flags:             101

Runtime Environment
  Current User:      marius
  Privileged:        no
  Shell:             /bin/bash
  Timezone:          America/Toronto
  Process Count:     35
  Container:         no
  Virtualized:       yes
  Virtualization:    subsystem
  PHP Version:       8.5.4
  PHP SAPI:          cli

Memory
  Total:             7.65 GiB
  Available:         7.10 GiB
  Free:              6.74 GiB
  Used:              568.43 MiB
  Swap Total:        2.00 GiB
  Swap Free:         2.00 GiB

Load Average
  1m:                0
  5m:                0
  15m:               0
```

## Available Data

- OS name, platform, version, hostname, kernel, architecture
- memory and swap/pagefile totals where available
- CPU model, vendor, cores, frequency, and flags where available
- runtime environment: user, privilege status, shell, timezone, process count, container/virtualization context, PHP version, PHP SAPI
- uptime and load average where the platform exposes them

Top-level array keys are available as constants on `OperatingSystemInfo`, such as
`OperatingSystemInfo::PLATFORM`, `OperatingSystemInfo::MEMORY`,
`OperatingSystemInfo::CPU`, and `OperatingSystemInfo::RUNTIME`.

## Target Platforms

- Linux
- macOS
- Windows

Other platforms may still return fallback values. More platforms to be supported soon.

## Development

```bash
composer dump-autoload --optimize --strict-psr
composer validate --strict
composer test
php bin/os-info --json
```
