<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Product',
    title: 'Product Resource',
    description: 'Public product details for catalog display',
    readOnly: true,
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 101),
        new OA\Property(property: 'name', type: 'string', example: 'Pupusas de Queso'),
        new OA\Property(property: 'slug', type: 'string', example: 'pupusas-de-queso'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 25.00),
        new OA\Property(property: 'formatted_price', type: 'string', example: 'L. 25.00'),
        new OA\Property(property: 'description', type: 'string', example: 'Deliciosas pupusas...', maxLength: 100),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', example: 'https://api.vitobusinessos.com/storage/products/1.jpg', nullable: true),
        new OA\Property(property: 'category', type: 'string', example: 'Desayunos', nullable: true),
        new OA\Property(property: 'stock_status', type: 'string', enum: ['in_stock', 'out_of_stock'], example: 'in_stock'),
    ]
)]
/**
 * @mixin \App\Models\Product
 */
final class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'formatted_price' => 'L. '.number_format((float) $product->price, 2),
            'description' => \Illuminate\Support\Str::limit($product->description ?? '', 100),
            'image_url' => $product->image_url,
            'category' => data_get($product, 'category.name'),
            'stock_status' => $product->stock_status->value ?? 'out_of_stock',
        ];
    }
}
