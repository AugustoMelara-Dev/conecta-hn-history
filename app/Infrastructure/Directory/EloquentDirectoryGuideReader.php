<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;

final class EloquentDirectoryGuideReader
{
    /**
     * @return array{answer:string,items:array<int,array<string,mixed>>,source:string,fallback:true,meta:array<string,mixed>}
     */
    public function answer(string $message, ?float $budget): array
    {
        $hasStatusColumn = Schema::hasColumn('products', 'status');

        $query = Product::query()
            // PUBLIC_READ_WITH_TENANT_FILTER: directory results expose only visible products from active tenants.
            ->withoutGlobalScopes()
            ->with(['tenant' => fn ($query) => $query->select(['id', 'name', 'slug', 'is_active'])])
            ->where('is_active', true)
            ->where('is_visible', true)
            ->whereHas('tenant', fn ($query) => $query->where('is_active', true))
            ->orderBy('price')
            ->limit(5);

        if ($hasStatusColumn) {
            $query->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', 'active');
            });
        }

        if ($budget !== null) {
            $query->where('price', '<=', $budget);
        }

        $products = $query->get(['id', 'tenant_id', 'name', 'slug', 'description', 'price']);
        $meta = [
            'llm_used' => false,
            'privacy_scope' => 'public_directory_only',
            'source' => 'public_directory',
            'fallback' => true,
        ];

        if ($products->isEmpty()) {
            return [
                'answer' => $budget === null
                    ? 'Todavia no encontre opciones publicas que coincidan. Proba con un producto, servicio o presupuesto mas especifico.'
                    : 'No encontre opciones publicas dentro de ese presupuesto. Proba ampliar el rango o revisar el directorio.',
                'items' => [],
                'source' => 'public_directory',
                'fallback' => true,
                'meta' => $meta,
            ];
        }

        $lines = $products->map(function (Product $product): string {
            $business = $product->tenant?->name ?? 'negocio publicado';

            return "{$product->name} en {$business} por L. ".number_format((float) $product->price, 2);
        });

        return [
            'answer' => 'Con datos publicos del directorio, podes revisar: '.$lines->implode('; ').'.',
            'items' => $products->map(fn (Product $product): array => [
                'type' => 'product',
                'name' => $product->name,
                'business_name' => $product->tenant?->name,
                'price' => (float) $product->price,
                'price_label' => 'L. '.number_format((float) $product->price, 2),
                'tenant' => [
                    'name' => $product->tenant?->name,
                    'slug' => $product->tenant?->slug,
                ],
                'url' => $product->tenant
                    ? route('tenant.public.show', ['tenant' => $product->tenant->slug], false)
                    : null,
            ])->values()->all(),
            'source' => 'public_directory',
            'fallback' => true,
            'meta' => $meta,
        ];
    }
}
