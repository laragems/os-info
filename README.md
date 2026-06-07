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

Example output from a macOS GitHub Actions runner:

```text
macOS 15.7.7 on arm64
3 logical cores, 7.00 GiB RAM
virtual_machine
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

Example output:

```text
Operating System
  Name:              macOS
  Platform:          macos
  Version:           15.7.7
  Version ID:        15.7.7
  Architecture:      arm64
  Raw Architecture:  arm64
  Hostname:          sat12-bq154-dda80348-68c7-4cbc-a98f-b4df31f86538-0EC0A85A70EE.local
  Kernel:            Darwin 24.6.0
  Container:         no
  Uptime:            1m

CPU
  Model:             Apple M1 (Virtual)
  Vendor:            n/a
  Architecture:      arm64
  Logical Cores:     3
  Physical Cores:    3
  Frequency:         n/a
  Flags:             0

Runtime Environment
  Current User:      runner
  Privileged:        no
  Shell:             /bin/bash
  Timezone:          UTC
  Process Count:     412
  Container:         no
  Virtualized:       yes
  Virtualization:    virtual_machine
  PHP Version:       8.1.34
  PHP SAPI:          cli

Memory
  Total:             7.00 GiB
  Available:         3.14 GiB
  Free:              n/a
  Used:              3.86 GiB
  Swap Total:        n/a
  Swap Free:         n/a

Load Average
  1m:                36.66455078125
  5m:                22.47216796875
  15m:               9.47900390625
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
