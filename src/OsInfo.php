<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

final class OsInfo
{
    /**
     * Detects operating system information for the current process.
     */
    public static function detect(): OperatingSystemInfo
    {
        return (new Detector())->detect();
    }
}

