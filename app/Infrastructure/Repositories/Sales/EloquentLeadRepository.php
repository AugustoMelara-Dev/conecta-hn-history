<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Domain\Sales\Entities\LeadEntity;
use App\Domain\Sales\Repositories\LeadRepositoryInterface;
use App\Infrastructure\Sales\Mappers\LeadMapper;
use App\Models\Lead;

/**
 * EloquentLeadRepository - Infrastructure Adapter for Lead Persistence
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DATA MAPPER: Converts Eloquent models to domain entities.              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentLeadRepository implements LeadRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createFromInteraction(
        string $tenantId,
        ?int $productId,
        string $source,
        ?string $message = null
    ): LeadEntity {
        $model = Lead::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'source' => $source,
            'message' => $message ?? 'Clic en WhatsApp desde el Catálogo',
            'status' => 'new',
        ]);

        return LeadMapper::toDomain($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?LeadEntity
    {
        $model = Lead::find($id);

        return $model ? LeadMapper::toDomain($model) : null;
    }
}
