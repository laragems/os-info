<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Value;

use Laragems\OsInfo\VirtualizationType;

final class RuntimeEnvironment
{
    public function __construct(
        private readonly ?string $currentUser = null,
        private readonly ?bool $isPrivileged = null,
        private readonly ?string $shell = null,
        private readonly ?string $timezone = null,
        private readonly ?int $processCount = null,
        private readonly bool $isContainer = false,
        private readonly ?bool $isVirtualized = null,
        private readonly VirtualizationType $virtualizationType = VirtualizationType::Unknown,
        private readonly string $phpVersion = PHP_VERSION,
        private readonly string $phpSapi = PHP_SAPI,
    ) {
        if ($this->processCount !== null && $this->processCount < 0) {
            throw new \InvalidArgumentException('Process count cannot be negative.');
        }

        if ($this->phpVersion === '') {
            throw new \InvalidArgumentException('PHP version cannot be empty.');
        }

        if ($this->phpSapi === '') {
            throw new \InvalidArgumentException('PHP SAPI cannot be empty.');
        }
    }

    public function currentUser(): ?string
    {
        return $this->currentUser;
    }

    public function isPrivileged(): ?bool
    {
        return $this->isPrivileged;
    }

    public function shell(): ?string
    {
        return $this->shell;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public function processCount(): ?int
    {
        return $this->processCount;
    }

    public function isContainer(): bool
    {
        return $this->isContainer;
    }

    public function isVirtualized(): ?bool
    {
        if ($this->isVirtualized !== null) {
            return $this->isVirtualized;
        }

        return match ($this->virtualizationType) {
            VirtualizationType::Container,
            VirtualizationType::VirtualMachine,
            VirtualizationType::Subsystem => true,
            VirtualizationType::BareMetal => false,
            VirtualizationType::Unknown => null,
        };
    }

    public function virtualizationType(): VirtualizationType
    {
        return $this->virtualizationType;
    }

    public function phpVersion(): string
    {
        return $this->phpVersion;
    }

    public function phpSapi(): string
    {
        return $this->phpSapi;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_user' => $this->currentUser(),
            'is_privileged' => $this->isPrivileged(),
            'shell' => $this->shell(),
            'timezone' => $this->timezone(),
            'process_count' => $this->processCount(),
            'is_container' => $this->isContainer(),
            'is_virtualized' => $this->isVirtualized(),
            'virtualization_type' => $this->virtualizationType()->value,
            'php_version' => $this->phpVersion(),
            'php_sapi' => $this->phpSapi(),
        ];
    }
}

