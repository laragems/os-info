<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Value;

final class MemoryInfo
{
    /**
     * Creates a memory information value object.
     */
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

    /**
     * Creates memory information from kilobyte values.
     */
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

    /**
     * Returns total memory in bytes.
     */
    public function totalBytes(): ?int
    {
        return $this->totalBytes;
    }

    /**
     * Returns available memory in bytes.
     */
    public function availableBytes(): ?int
    {
        return $this->availableBytes;
    }

    /**
     * Returns free memory in bytes.
     */
    public function freeBytes(): ?int
    {
        return $this->freeBytes;
    }

    /**
     * Returns used memory in bytes.
     */
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

    /**
     * Returns total swap or pagefile memory in bytes.
     */
    public function swapTotalBytes(): ?int
    {
        return $this->swapTotalBytes;
    }

    /**
     * Returns free swap or pagefile memory in bytes.
     */
    public function swapFreeBytes(): ?int
    {
        return $this->swapFreeBytes;
    }

    /**
     * Returns memory information as an array payload.
     *
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

    /**
     * Converts kilobytes into bytes.
     */
    private static function kilobytesToBytes(?int $kilobytes): ?int
    {
        return $kilobytes === null ? null : $kilobytes * 1024;
    }
}

