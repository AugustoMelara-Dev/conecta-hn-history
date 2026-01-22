<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderItem',
    title: 'Order Item',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 55),
        new OA\Property(property: 'product_name', type: 'string', example: 'Pizza Margherita'),
        new OA\Property(property: 'quantity', type: 'integer', example: 1),
        new OA\Property(property: 'unit_price', type: 'number', format: 'float', example: 120.00),
        new OA\Property(property: 'formatted_price', type: 'string', example: 'L. 120.00'),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 120.00),
        new OA\Property(property: 'options', type: 'object', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ]
)]
class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->name, // Model attribute is 'name' (cached from product)
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->price,
            'formatted_price' => 'L. '.number_format((float) $this->price, 2),
            'total' => (float) $this->total,
            'options' => $this->options,
            'notes' => $this->notes,
        ];
    }
}
