<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales\Mappers;

use App\Domain\Sales\Entities\LeadEntity;
use App\Models\Lead as LeadModel;

/**
 * LeadMapper - Infrastructure bridge to Domain
 *
 * RESPONSIBILITY: Hydrate Domain Entities from Eloquent Models.
 * DEPENDENCY DIRECTION: Infrastructure -> Domain (Correct).
 */
final class LeadMapper
{
    public static function toDomain(LeadModel $model): LeadEntity
    {
        $name = $model->getAttribute('customer_name') ?? $model->getAttribute('name') ?? 'Lead';
        $phone = $model->getAttribute('customer_phone') ?? $model->getAttribute('phone');
        $message = $model->getAttribute('message');
        $status = $model->getAttribute('status') ?? 'new';
        $productId = $model->getAttribute('product_id');
        $source = $model->getAttribute('source') ?? 'website';

        return new LeadEntity(
            id: (int) $model->getKey(),
            tenantId: (string) ($model->getAttribute('tenant_id') ?? ''),
            source: (string) $source,
            contactData: [
                'name' => (string) $name,
                'phone' => $phone !== null ? (string) $phone : null,
                'message' => $message !== null ? (string) $message : null,
                'status' => (string) $status,
                'product_id' => is_numeric($productId) ? (int) $productId : null,
            ],
        );
    }
}
