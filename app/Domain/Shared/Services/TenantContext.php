<?php

declare(strict_types=1);

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Exceptions\TenantContextMissingException;
use App\Domain\Shared\Exceptions\TenantContextMutationException;
use LogicException;

/**
 * TenantContext V2 - Strict Singleton State Machine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ZERO TRUST ARCHITECTURE: Fail Secure & State Determinism                ║
 * ║  SOURCE OF TRUTH: Holds the Tenant ID for the current request cycle.     ║
 * ║  IMMUTABLE: Set once per request. Cannot be mutated without reset().     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class TenantContext
{
    private ?int $tenantId = null;

    private bool $isSystemRoot = false;

    private bool $isInitialized = false;

    /**
     * Initialize context for a specific Tenant.
     *
     * @throws TenantContextMutationException If attempting to switch tenants.
     */
    public function setTenant(int $id): void
    {
        if ($this->isInitialized && $this->tenantId !== null && $this->tenantId !== $id) {
            throw TenantContextMutationException::tenantHopping($this->tenantId, $id);
        }

        if ($this->isInitialized && $this->isSystemRoot) {
            throw TenantContextMutationException::alreadyInitialized();
        }

        $this->tenantId = $id;
        $this->isInitialized = true;
    }

    /**
     * Initialize context as System Root (Super Admin / Console / Jobs).
     *
     * @throws TenantContextMutationException If context already set.
     */
    public function setAsSystemRoot(): void
    {
        if ($this->isInitialized && ! $this->isSystemRoot) {
            throw TenantContextMutationException::alreadyInitialized();
        }

        $this->isSystemRoot = true;
        $this->isInitialized = true;
    }

    /**
     * Get the current Tenant ID.
     *
     * @throws LogicException If called in System Root mode (no ID exists).
     * @throws TenantContextMissingException If context is not initialized.
     */
    public function id(): int
    {
        if ($this->isSystemRoot) {
            throw new LogicException('Root context has no specific ID. Use isRoot() to check mode.');
        }

        if (! $this->isInitialized || $this->tenantId === null) {
            throw TenantContextMissingException::uninitialized();
        }

        return $this->tenantId;
    }

    /**
     * Check if context is in System Root mode.
     */
    public function isRoot(): bool
    {
        return $this->isSystemRoot;
    }

    /**
     * Check if context is initialized.
     */
    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    /**
     * Reset the context (Testing/Workers/Octane only).
     */
    public function reset(): void
    {
        $this->tenantId = null;
        $this->isSystemRoot = false;
        $this->isInitialized = false;
    }
}
