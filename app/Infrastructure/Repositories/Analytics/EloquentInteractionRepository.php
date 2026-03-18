<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Analytics;

use App\Domain\Analytics\Entities\InteractionEntity;
use App\Domain\Analytics\Repositories\InteractionRepositoryInterface;
use App\Models\Interaction;
use DateTimeImmutable;

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
        string $tenantId,
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
        string $tenantId,
        string $visitorId,
        string $type,
        ?string $productId = null,
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
        $createdAt = $model->created_at;

        return new InteractionEntity(
            id: (int) $model->getKey(),
            tenantId: (string) ($model->getAttribute('tenant_id') ?? ''),
            type: (string) $model->type,
            productId: is_numeric($model->getAttribute('product_id')) ? (int) $model->getAttribute('product_id') : null,
            ipAddress: ($value = $model->getAttribute('ip_address')) !== null ? (string) $value : null,
            userAgent: ($value = $model->getAttribute('user_agent')) !== null ? (string) $value : null,
            referrer: ($value = $model->getAttribute('referrer')) !== null ? (string) $value : null,
            url: ($value = $model->getAttribute('url')) !== null ? (string) $value : null,
            metadata: is_array($model->getAttribute('metadata')) ? $model->getAttribute('metadata') : [],
            createdAt: $createdAt !== null
                ? DateTimeImmutable::createFromInterface($createdAt)
                : new DateTimeImmutable,
        );
    }
}
