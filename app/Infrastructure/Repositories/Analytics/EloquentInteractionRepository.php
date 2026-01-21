<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Analytics;

use App\Domain\Analytics\Entities\InteractionEntity;
use App\Domain\Analytics\Repositories\InteractionRepositoryInterface;
use App\Models\Interaction;
use Carbon\Carbon;

/**
 * EloquentInteractionRepository - Infrastructure Adapter for Interaction Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DATA MAPPER: Converts Eloquent models to domain entities.              ║
 * ║  VISITOR-SEGMENTED: Deduplication is per-visitor, not global.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentInteractionRepository implements InteractionRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function hasRecentInteraction(
        int $tenantId,
        string $visitorId,
        string $type,
        int $withinMinutes = 5
    ): bool {
        return Interaction::query()
            ->where('tenant_id', $tenantId)
            ->where('visitor_id', $visitorId) // CRITICAL: Per-visitor deduplication
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes($withinMinutes))
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function create(
        int $tenantId,
        string $visitorId,
        string $type,
        ?int $productId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null,
        ?string $url = null,
        array $metadata = []
    ): InteractionEntity {
        $model = Interaction::create([
            'tenant_id' => $tenantId,
            'visitor_id' => $visitorId,
            'type' => $type,
            'product_id' => $productId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referrer' => $referrer,
            'url' => $url,
            'metadata' => $metadata,
        ]);

        return $this->toEntity($model);
    }

    /**
     * Map Eloquent model to domain entity.
     */
    private function toEntity(Interaction $model): InteractionEntity
    {
        return new InteractionEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            type: $model->type,
            productId: $model->product_id,
            ipAddress: $model->ip_address,
            userAgent: $model->user_agent,
            referrer: $model->referrer,
            url: $model->url,
            metadata: $model->metadata ?? [],
            createdAt: Carbon::parse($model->created_at),
        );
    }
}
