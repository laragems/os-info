<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

final class OsInfo
{
    public static function detect(): OperatingSystemInfo
    {
        return (new Detector())->detect();
    }
}

