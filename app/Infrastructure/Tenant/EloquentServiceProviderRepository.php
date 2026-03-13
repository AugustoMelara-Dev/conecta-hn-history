<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Application\Shared\Contracts\ServiceProviderRepositoryInterface;
use App\Models\ServiceProvider;
use Spatie\OpeningHours\OpeningHours;

class EloquentServiceProviderRepository implements ServiceProviderRepositoryInterface
{
    public function findById(string|int $id): ?object
    {
        return ServiceProvider::find($id);
    }

    public function getActiveForTenant(string|int $tenantId): array
    {
        return ServiceProvider::where('tenant_id', $tenantId)
            ->active()
            ->get()
            ->toArray();
    }

    public function isAvailableAt(string|int $providerId, \DateTimeInterface $start, \DateTimeInterface $end, int $bufferBefore = 0, int $bufferAfter = 0): bool
    {
        $provider = ServiceProvider::find($providerId);

        if (! $provider) {
            return false;
        }

        return $provider->isAvailableAt($start, $end, $bufferBefore, $bufferAfter);
    }

    public function getOpeningHours(string|int $providerId): ?OpeningHours
    {
        $provider = ServiceProvider::find($providerId);

        return $provider?->getOpeningHours();
    }
}
