<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CrmDealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->crm_pipeline_id,
            'stage_id' => $this->crm_stage_id,
            'customer_profile_id' => $this->customer_profile_id,
            'lead_id' => $this->lead_id,
            'owner_user_id' => $this->owner_user_id,
            'location_id' => $this->location_id,
            'title' => $this->title,
            'value' => $this->value,
            'currency' => $this->currency,
            'status' => $this->status,
            'source' => $this->source,
            'expected_close_at' => $this->expected_close_at?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customerProfile', fn (): ?array => $this->customerProfile ? [
                'id' => $this->customerProfile->id,
                'name' => $this->customerProfile->name,
                'email' => $this->customerProfile->email,
                'phone' => $this->customerProfile->phone,
            ] : null),
            'lead' => $this->whenLoaded('lead', fn (): ?array => $this->lead ? [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
                'email' => $this->lead->email,
                'phone' => $this->lead->phone,
            ] : null),
            'stage' => $this->whenLoaded('stage', fn () => new CrmStageResource($this->stage)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
