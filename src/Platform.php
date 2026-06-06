<?php

declare(strict_types=1);

namespace Laragems\OsInfo;

enum Platform: string
{
    case Linux = 'linux';
    case MacOS = 'macos';
    case Windows = 'windows';
    case BSD = 'bsd';
    case Solaris = 'solaris';
    case Unknown = 'unknown';

    public static function fromPhpOsFamily(string $family): self
    {
        return match (strtolower($family)) {
            'linux' => self::Linux,
            'darwin' => self::MacOS,
            'windows' => self::Windows,
            'bsd' => self::BSD,
            'solaris' => self::Solaris,
            default => self::Unknown,
        };
    }
}

