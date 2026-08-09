<?php

declare(strict_types=1);

namespace App\Infrastructure\Interactions\Persistence;

use App\Domain\Analytics\Entities\InteractionEntity;
use App\Domain\Interactions\Repositories\InteractionRepositoryInterface;
use App\Models\Interaction;
use Illuminate\Support\Carbon;

final readonly class EloquentInteractionRepository implements InteractionRepositoryInterface
{
    public function create(InteractionEntity $entity): void
    {
        Interaction::query()->create([
            'id' => $entity->id,
            'tenant_id' => $entity->tenantId,
            'type' => $entity->type,
            'product_id' => $entity->productId,
            'ip_address' => $entity->ipAddress,
            'user_agent' => $entity->userAgent,
            'referrer' => $entity->referrer,
            'url' => $entity->url,
            'metadata' => $entity->metadata,
            'created_at' => $entity->createdAt,
            'updated_at' => $entity->createdAt,
        ]);
    }

    public function exists(string $tenantId, array $criteria): bool
    {
        $query = Interaction::query()->where('tenant_id', $tenantId);

        foreach ($criteria as $key => $value) {
            if ($key === 'created_at' && $value instanceof Carbon) {
                // Handle date comparison specifically for created_at
                $query->whereDate($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->exists();
    }
}
