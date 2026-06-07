<?php

declare(strict_types=1);

/**
 * Registers the package autoloader for non-Composer usage.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Laragems\\OsInfo\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
        . '.php';

    if (is_file($file)) {
        require $file;
    }
});
