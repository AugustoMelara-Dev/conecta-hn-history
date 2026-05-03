<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CrmPipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'description' => $this->description,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'stages' => CrmStageResource::collection($this->whenLoaded('stages')),
        ];
    }
}
