<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderResource - API Contract for Orders
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONTRACT ISOLATION: snake_case API vs camelCase Domain DTO              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Explicit Mapping to prevent leakage
        // $this->resource is CreateOrderResult (DTO) or Order (Entity)
        // If it's a DTO, properties are public readonly.
        // If it's an Eloquent Model, properties are magic accessors.
        // The Spec requests "Mapea explícitamente cada propiedad del DTO CreateOrderResult".
        // Assuming OrderController returns `OrderResource` wrapping `CreateOrderResult`.

        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'order_number' => $resource->orderNumber ?? $resource->order_number, // Fallback for Entity
            'status' => $resource->status,
            'amount' => $resource->totalAmount ?? (float) ($resource->total ?? 0),
            'currency' => $resource->currency ?? 'HNL',
            'item_count' => $resource->itemsCount,
            'created_at' => $resource->createdAt ?? $resource->created_at ?? null,
            // 'items' => OrderItemResource::collection($this->whenLoaded('items')), // If needed
        ];
    }
}
