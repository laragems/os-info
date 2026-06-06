<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Value;

final class CpuInfo
{
    /**
     * @param list<string> $flags
     */
    public function __construct(
        private readonly string $architecture,
        private readonly ?string $modelName = null,
        private readonly ?string $vendor = null,
        private readonly ?int $logicalCores = null,
        private readonly ?int $physicalCores = null,
        private readonly ?float $frequencyMHz = null,
        private readonly array $flags = [],
    ) {
        if ($this->architecture === '') {
            throw new \InvalidArgumentException('CPU architecture cannot be empty.');
        }

        foreach ([
            'logicalCores' => $this->logicalCores,
            'physicalCores' => $this->physicalCores,
        ] as $name => $value) {
            if ($value !== null && $value < 1) {
                throw new \InvalidArgumentException(sprintf('CPU value "%s" must be positive.', $name));
            }
        }

        if ($this->frequencyMHz !== null && $this->frequencyMHz < 0) {
            throw new \InvalidArgumentException('CPU frequency cannot be negative.');
        }
    }

    public function architecture(): string
    {
        return $this->architecture;
    }

    public function modelName(): ?string
    {
        return $this->modelName;
    }

    public function vendor(): ?string
    {
        return $this->vendor;
    }

    public function logicalCores(): ?int
    {
        return $this->logicalCores;
    }

    public function physicalCores(): ?int
    {
        return $this->physicalCores;
    }

    public function frequencyMHz(): ?float
    {
        return $this->frequencyMHz;
    }

    /**
     * @return list<string>
     */
    public function flags(): array
    {
        return $this->flags;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'architecture' => $this->architecture(),
            'model_name' => $this->modelName(),
            'vendor' => $this->vendor(),
            'logical_cores' => $this->logicalCores(),
            'physical_cores' => $this->physicalCores(),
            'frequency_mhz' => $this->frequencyMHz(),
            'flags' => $this->flags(),
        ];
    }
}

