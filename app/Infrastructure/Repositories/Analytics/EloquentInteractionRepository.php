<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Analytics;

use App\Domain\Analytics\Entities\InteractionEntity;
use App\Domain\Analytics\Repositories\InteractionRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
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
        return $this->trustedTenantQuery()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($visitorId): void {
                $query
                    ->where('visitor_id', $visitorId)
                    ->orWhere('session_id', $visitorId)
                    ->orWhere('metadata->session_id', $visitorId);
            })
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
        array $attribution = [],
        array $metadata = []
    ): InteractionEntity {
        $model = new Interaction;
        $model->forceFill([
            'tenant_id' => $tenantId,
            'visitor_id' => $visitorId,
            'session_id' => $visitorId,
            'type' => $type,
            'signal_class' => $type === 'microsite_view' ? 'traffic' : 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
            'product_id' => $productId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referrer' => $referrer,
            'url' => $url,
            'utm_source' => $this->stringAttribution($attribution, 'utm_source'),
            'utm_medium' => $this->stringAttribution($attribution, 'utm_medium'),
            'utm_campaign' => $this->stringAttribution($attribution, 'utm_campaign'),
            'utm_term' => $this->stringAttribution($attribution, 'utm_term'),
            'utm_content' => $this->stringAttribution($attribution, 'utm_content'),
            'metadata' => $metadata,
        ]);
        $model->save();

        return $this->toEntity($model);
    }

    private function trustedTenantQuery()
    {
        // Public analytics requests arrive without an initialized TenantContext.
        // This repository already receives an explicit tenant identifier, so we
        // bypass only the global tenant scope and keep the rest of the model
        // behavior intact.
        // INFRA_TENANT_FILTER: callers append the repository's mandatory tenant UUID predicate before execution.
        return Interaction::query()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @param  array<string, mixed>  $attribution
     */
    private function stringAttribution(array $attribution, string $key): ?string
    {
        $value = $attribution[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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
