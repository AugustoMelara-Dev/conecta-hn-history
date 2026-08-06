<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'pipeline_id' => $this->pipeline_id,
            'stage_id' => $this->stage_id,
            'contact_id' => $this->contact_id,
            'company_id' => $this->company_id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'probability' => $this->probability,
            'weighted_value' => $this->weighted_value,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'status' => $this->status,
            'won_at' => $this->won_at?->toIso8601String(),
            'lost_at' => $this->lost_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'description' => $this->description,
            'custom_fields' => $this->custom_fields ?? new \stdClass(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'contact' => $this->whenLoaded('contact'),
            'company' => $this->whenLoaded('company'),
            'owner' => $this->whenLoaded('owner'),
            'pipeline' => $this->whenLoaded('pipeline'),
            'stage' => $this->whenLoaded('stage'),
        ];
    }
}
