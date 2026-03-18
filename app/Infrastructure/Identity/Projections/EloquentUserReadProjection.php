<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Projections;

use App\Application\Identity\Contracts\UserReadProjection;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Models\User;

/**
 * EloquentUserReadProjection - Infrastructure Implementation
 *
 * ADAPTER: Implements the UserReadProjection port using Eloquent ORM.
 * This is the ONLY place in the system where User::findOrFail() may
 * be called for Action-layer read operations.
 *
 * For microservices extraction, replace this with an API/gRPC implementation.
 */
final readonly class EloquentUserReadProjection implements UserReadProjection
{
    public function getById(string $userId, ?string $tenantId = null): TenantAwareAuthenticatable
    {
        $query = User::query();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->findOrFail($userId);
    }
}
