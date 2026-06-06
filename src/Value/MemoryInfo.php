<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Value;

final class MemoryInfo
{
    public function __construct(
        private readonly ?int $totalBytes = null,
        private readonly ?int $availableBytes = null,
        private readonly ?int $freeBytes = null,
        private readonly ?int $usedBytes = null,
        private readonly ?int $swapTotalBytes = null,
        private readonly ?int $swapFreeBytes = null,
    ) {
        foreach ([
            'totalBytes' => $this->totalBytes,
            'availableBytes' => $this->availableBytes,
            'freeBytes' => $this->freeBytes,
            'usedBytes' => $this->usedBytes,
            'swapTotalBytes' => $this->swapTotalBytes,
            'swapFreeBytes' => $this->swapFreeBytes,
        ] as $name => $value) {
            if ($value !== null && $value < 0) {
                throw new \InvalidArgumentException(sprintf('Memory value "%s" cannot be negative.', $name));
            }
        }
    }

    public static function fromKilobytes(
        ?int $totalKilobytes = null,
        ?int $availableKilobytes = null,
        ?int $freeKilobytes = null,
        ?int $swapTotalKilobytes = null,
        ?int $swapFreeKilobytes = null,
    ): self {
        return new self(
            totalBytes: self::kilobytesToBytes($totalKilobytes),
            availableBytes: self::kilobytesToBytes($availableKilobytes),
            freeBytes: self::kilobytesToBytes($freeKilobytes),
            swapTotalBytes: self::kilobytesToBytes($swapTotalKilobytes),
            swapFreeBytes: self::kilobytesToBytes($swapFreeKilobytes),
        );
    }

    public function totalBytes(): ?int
    {
        return $this->totalBytes;
    }

    public function availableBytes(): ?int
    {
        return $this->availableBytes;
    }

    public function freeBytes(): ?int
    {
        return $this->freeBytes;
    }

    public function usedBytes(): ?int
    {
        if ($this->usedBytes !== null) {
            return $this->usedBytes;
        }

        if ($this->totalBytes === null) {
            return null;
        }

        $availableOrFree = $this->availableBytes ?? $this->freeBytes;

        if ($availableOrFree === null) {
            return null;
        }

        return max(0, $this->totalBytes - $availableOrFree);
    }

    public function swapTotalBytes(): ?int
    {
        return $this->swapTotalBytes;
    }

    public function swapFreeBytes(): ?int
    {
        return $this->swapFreeBytes;
    }

    /**
     * @return array<string, ?int>
     */
    public function toArray(): array
    {
        return [
            'total_bytes' => $this->totalBytes(),
            'available_bytes' => $this->availableBytes(),
            'free_bytes' => $this->freeBytes(),
            'used_bytes' => $this->usedBytes(),
            'swap_total_bytes' => $this->swapTotalBytes(),
            'swap_free_bytes' => $this->swapFreeBytes(),
        ];
    }

    private static function kilobytesToBytes(?int $kilobytes): ?int
    {
        return $kilobytes === null ? null : $kilobytes * 1024;
    }
}

