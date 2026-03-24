<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Domain\Sales\Entities\LeadEntity;
use App\Domain\Sales\Repositories\LeadRepositoryInterface;
use App\Infrastructure\Sales\Mappers\LeadMapper;
use App\Models\Lead;

final class EloquentLeadRepository implements LeadRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createFromInteraction(
        string $tenantId,
        ?int $productId,
        string $source,
        ?string $message = null,
        ?string $sessionId = null
    ): LeadEntity {
        $model = Lead::create([
            'tenant_id' => $tenantId,
            'name' => $source === 'whatsapp_click' ? 'Lead desde WhatsApp' : 'Lead web',
            'product_id' => $productId,
            'source' => $source,
            'session_id' => $sessionId,
            'message' => $message ?? 'Clic en WhatsApp desde el catalogo',
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
