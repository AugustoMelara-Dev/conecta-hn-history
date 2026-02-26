<?php

declare(strict_types=1);

namespace App\Infrastructure\Interactions\Persistence;

use App\Domain\Interactions\Repositories\InteractionRepositoryInterface;
use App\Models\Interaction;

final readonly class EloquentInteractionRepository implements InteractionRepositoryInterface
{
    public function create(array $data, string $tenantId): Interaction
    {
        // Enforce strict tenant isolation
        return Interaction::query()->create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);
    }

    public function exists(string $tenantId, array $criteria): bool
    {
        // Enforce strict tenant isolation
        $query = Interaction::query()->where('tenant_id', $tenantId);

        foreach ($criteria as $key => $value) {
            if ($key === 'created_at' && $value instanceof \Illuminate\Support\Carbon) {
                // Handle date comparison specifically for created_at
                $query->whereDate($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->exists();
    }
}
