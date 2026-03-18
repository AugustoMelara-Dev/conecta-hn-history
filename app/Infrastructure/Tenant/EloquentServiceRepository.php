<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Application\Shared\Contracts\ServiceRepositoryInterface;
use App\Models\Service;

final class EloquentServiceRepository implements ServiceRepositoryInterface
{
    public function findById(string|int $id): ?Service
    {
        return Service::find($id);
    }

    public function getActiveForTenant(string|int $tenantId): array
    {
        return Service::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'formatted_price' => $service->formatted_price,
                'duration_minutes' => $service->duration_minutes,
                'formatted_duration' => $service->formatted_duration,
            ])
            ->all();
    }
}
