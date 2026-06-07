# Laragems OS Info

`laragems/os-info` detects operating system information from plain PHP.
It is framework-free and has no third-party runtime dependencies.

## Install With Composer

```bash
composer require laragems/os-info
```

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Laragems\OsInfo\OsInfo;

$info = OsInfo::detect();

echo $info->name();              // Ubuntu, macOS, Microsoft Windows 11 Pro
echo $info->platform()->value;   // linux, macos, windows
echo $info->architecture();      // x86_64, arm64, x86
echo $info->memory()->totalBytes();
echo $info->cpu()->logicalCores();
echo $info->runtime()->currentUser();
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

## Available Data

- OS name, platform, version, hostname, kernel, architecture
- memory and swap/pagefile totals where available
- CPU model, vendor, cores, frequency, and flags where available
- runtime environment: user, privilege status, shell, timezone, process count, container/virtualization context, PHP version, PHP SAPI
- uptime and load average where the platform exposes them

Top-level array keys are available as constants on `OperatingSystemInfo`, such as
`OperatingSystemInfo::PLATFORM`, `OperatingSystemInfo::MEMORY`,
`OperatingSystemInfo::CPU`, and `OperatingSystemInfo::RUNTIME`.

## Development

```bash
composer dump-autoload
composer validate --strict
composer test
php bin/os-info --json
```
