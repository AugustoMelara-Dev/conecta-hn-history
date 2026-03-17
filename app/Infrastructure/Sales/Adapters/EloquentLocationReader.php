<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales\Adapters;

use App\Domain\Inventory\Contracts\LocationReaderInterface;
use App\Models\Location;

final readonly class EloquentLocationReader implements LocationReaderInterface
{
    public function existsActiveForTenant(string $locationId, string $tenantId): bool
    {
        return Location::query()
            ->where('id', $locationId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }
}
