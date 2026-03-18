<?php

declare(strict_types=1);

namespace App\Application\Identity\Contracts;

use App\Application\Shared\Contracts\TenantAwareAuthenticatable;

/**
 * UserReadProjection - Read-Side User Retrieval Port
 *
 * Replaces User::findOrFail() calls in Actions/Infrastructure adapters.
 * Decouples the read path from Active Record, enabling:
 * - CQRS read model separation
 * - Cache-backed projections
 * - Microservices extraction (API/gRPC backed implementations)
 *
 * P3 COMPLIANCE: CreateNewUser's Fortify bridge uses this
 * instead of User::findOrFail() to return the required model.
 */
interface UserReadProjection
{
    /**
     * Retrieve a user by their ID, optionally scoped to a tenant.
     *
     * @param  string  $userId  The user's UUID
     * @param  string|null  $tenantId  Tenant scope for isolation
     */
    public function getById(string $userId, ?string $tenantId = null): TenantAwareAuthenticatable;
}
