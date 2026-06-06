<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Support;

final class ArchitectureNormalizer
{
    public static function normalize(string $architecture): string
    {
        $normalized = strtolower(trim($architecture));
        $normalized = str_replace([' ', '-'], ['', '_'], $normalized);

        return match ($normalized) {
            'amd64', 'x64', 'x8664', 'x86_64' => 'x86_64',
            'i386', 'i486', 'i586', 'i686', 'x86' => 'x86',
            'aarch64', 'arm64' => 'arm64',
            'armv6l', 'armv6' => 'armv6',
            'armv7l', 'armv7' => 'armv7',
            'armv8l', 'armv8' => 'armv8',
            'ppc64le', 'powerpc64le' => 'ppc64le',
            'ppc64', 'powerpc64' => 'ppc64',
            's390x' => 's390x',
            'riscv64' => 'riscv64',
            '' => 'unknown',
            default => $normalized,
        };
    }
}

