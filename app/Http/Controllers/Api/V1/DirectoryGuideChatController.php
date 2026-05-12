<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class DirectoryGuideChatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:500'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $message = (string) $validated['message'];
        $budget = isset($validated['budget'])
            ? (float) $validated['budget']
            : $this->extractBudget($message);

        $hasStatusColumn = Schema::hasColumn('products', 'status');

        $query = Product::query()
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

        if ($products->isEmpty()) {
            return response()->json([
                'answer' => $budget === null
                    ? 'Todavia no encontre opciones publicas que coincidan. Proba con un producto, servicio o presupuesto mas especifico.'
                    : 'No encontre opciones publicas dentro de ese presupuesto. Proba ampliar el rango o revisar el directorio.',
                'items' => [],
                'source' => 'public_directory',
                'fallback' => true,
                'meta' => $this->meta(),
            ]);
        }

        $lines = $products->map(function (Product $product): string {
            $tenant = $product->tenant;
            $business = $tenant?->name ?? 'negocio publicado';

            return "{$product->name} en {$business} por L. ".number_format((float) $product->price, 2);
        });

        return response()->json([
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
                    ? route('tenant.microsite.show', ['tenant' => $product->tenant->slug], false)
                    : null,
            ])->values(),
            'source' => 'public_directory',
            'fallback' => true,
            'meta' => $this->meta(),
        ]);
    }

    /**
     * @return array{llm_used:false,privacy_scope:string,source:string,fallback:true}
     */
    private function meta(): array
    {
        return [
            'llm_used' => false,
            'privacy_scope' => 'public_directory_only',
            'source' => 'public_directory',
            'fallback' => true,
        ];
    }

    private function extractBudget(string $message): ?float
    {
        if (! preg_match('/(?:l\.?|lempiras?|hnl)?\s*(\d+(?:[.,]\d{1,2})?)/i', $message, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[1]);
    }
}
