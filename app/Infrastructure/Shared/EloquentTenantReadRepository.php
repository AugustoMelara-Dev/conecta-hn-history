<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\Contracts\TenantReadRepository;
use App\Models\Tenant;

/**
 * EloquentTenantReadRepository - Implementation
 */
final class EloquentTenantReadRepository implements TenantReadRepository
{
    public function exists(int $id): bool
    {
        return Tenant::where('id', $id)->exists();
    }
}
