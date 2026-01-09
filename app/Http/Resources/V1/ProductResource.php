<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource - API Contract for Products
 */
final class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'sku' => $resource->sku,
            'name' => $resource->name,
            'price' => number_format((float) ($resource->price ?? 0), 2, '.', ''),
            'is_active' => $resource->isVisible ?? $resource->is_visible ?? false,
            'changed_attributes' => $resource->changedAttributes ?? [], // Only for Updates DTO
        ];
    }
}
