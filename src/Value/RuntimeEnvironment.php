<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Value;

use Laragems\OsInfo\VirtualizationType;

final class RuntimeEnvironment
{
    /**
     * Creates a runtime environment value object.
     */
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

    /**
     * Returns the current process user.
     */
    public function currentUser(): ?string
    {
        return $this->currentUser;
    }

    /**
     * Returns whether the process has elevated privileges.
     */
    public function isPrivileged(): ?bool
    {
        return $this->isPrivileged;
    }

    /**
     * Returns the current shell or command interpreter path.
     */
    public function shell(): ?string
    {
        return $this->shell;
    }

    /**
     * Returns the system or PHP timezone identifier.
     */
    public function timezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Returns the current process count.
     */
    public function processCount(): ?int
    {
        return $this->processCount;
    }

    /**
     * Returns whether the runtime appears to be in a container.
     */
    public function isContainer(): bool
    {
        return $this->isContainer;
    }

    /**
     * Returns whether the runtime appears virtualized.
     */
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

    /**
     * Returns the broad virtualization category.
     */
    public function virtualizationType(): VirtualizationType
    {
        return $this->virtualizationType;
    }

    /**
     * Returns the PHP runtime version.
     */
    public function phpVersion(): string
    {
        return $this->phpVersion;
    }

    /**
     * Returns the PHP SAPI name.
     */
    public function phpSapi(): string
    {
        return $this->phpSapi;
    }

    /**
     * Returns runtime information as an array payload.
     *
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

