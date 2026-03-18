<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * TenantContext - Domain Contract for Tenant Resolution
 *
 * ARCHITECTURE:
 * Abstract wrapper to resolve the current Tenant ID.
 * Decouples Domain/Application access from HTTP Request/Auth logic.
 */
interface TenantContext
{
    /**
     * Get the current Tenant UUID (Option 2 Extension).
     *
     * @throws \RuntimeException If context cannot be resolved.
     */
    public function getTenantUuid(): string;

    /**
     * Get the current Tenant ID.
     *
     * @throws \RuntimeException If context cannot be resolved.
     *
     * @deprecated Use getTenantUuid() once Option 1 full migration is executed.
     */
    public function getTenantId(): int;

    /**
     * Get tenant data as an array (for view/response serialization).
     *
     * @param  array|null  $only  Optional keys to include (e.g., ['id', 'name', 'slug'])
     */
    public function getTenantData(?array $only = null): array;
}
