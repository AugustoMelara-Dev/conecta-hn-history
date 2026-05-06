<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CrmStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->crm_pipeline_id,
            'name' => $this->name,
            'key' => $this->key,
            'type' => $this->type,
            'probability' => $this->probability,
            'is_default' => (bool) $this->is_default,
            'sort_order' => $this->sort_order,
        ];
    }
}
