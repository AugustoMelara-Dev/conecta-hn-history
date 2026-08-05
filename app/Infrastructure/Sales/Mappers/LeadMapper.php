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
        return new LeadEntity(
            id: $model->id,
            tenantId: (string) $model->tenant_id,
            source: $model->source,
            contactData: [
                'name' => $model->customer_name,
                'phone' => $model->customer_phone,
                'message' => $model->message,
                'status' => $model->status,
                'product_id' => $model->product_id,
            ],
        );
    }
}
