<?php

declare(strict_types=1);

namespace App\Domain\Sales\Entities;

use App\Models\Lead;
use Carbon\Carbon;

/**
 * LeadEntity - Pure Domain Entity for Sales Lead
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: No Eloquent methods (save/delete/update) exposed.                ║
 * ║  IMMUTABLE: Readonly properties, no setters.                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LeadEntity
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $source,
        public array $contactData,
    ) {}

    /**
     * Hydrate entity from Eloquent model.
     */
    public static function fromModel(Lead $model): self
    {
        return new self(
            id: $model->id,
            tenantId: $model->tenant_id,
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
