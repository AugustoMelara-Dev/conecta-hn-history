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
        ?string $sessionId = null,
        array $attribution = [],
    ): LeadEntity {
        $model = new Lead;
        $model->forceFill([
            'tenant_id' => $tenantId,
            'name' => $source === 'whatsapp_click' ? 'Lead desde WhatsApp' : 'Lead web',
            'product_id' => $productId,
            'source' => $source,
            'session_id' => $sessionId,
            'utm_source' => $this->stringAttribution($attribution, 'utm_source'),
            'utm_medium' => $this->stringAttribution($attribution, 'utm_medium'),
            'utm_campaign' => $this->stringAttribution($attribution, 'utm_campaign'),
            'utm_term' => $this->stringAttribution($attribution, 'utm_term'),
            'utm_content' => $this->stringAttribution($attribution, 'utm_content'),
            'message' => $message ?? 'Clic en WhatsApp desde el catalogo',
            'status' => 'new',
        ]);
        $model->save();

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

    /**
     * @param  array<string, mixed>  $attribution
     */
    private function stringAttribution(array $attribution, string $key): ?string
    {
        $value = $attribution[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
