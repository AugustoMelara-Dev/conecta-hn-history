<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class DirectoryGuideService
{
    /**
     * @return array{
     *     answer:string,
     *     suggestions:array<int, array<string, mixed>>,
     *     items:array<int, array<string, mixed>>,
     *     meta:array<string, mixed>
     * }
     */
    public function answer(string $message, ?float $budget = null, ?string $city = null, ?string $category = null): array
    {
        $term = $this->normalizeTerm($message);
        $products = $this->publicProducts($term, $budget, $city, $category);
        $services = $this->publicServices($term, $budget, $city, $category);

        $items = $products
            ->merge($services)
            ->sortBy([
                ['price', 'asc'],
                ['business_name', 'asc'],
            ])
            ->take(6)
            ->values();

        if ($items->isEmpty()) {
            return [
                'answer' => $this->emptyAnswer($budget, $city, $category),
                'suggestions' => [
                    ['label' => 'Ver todos los negocios', 'href' => route('directory.index', absolute: false)],
                    ['label' => 'Quitar filtros', 'href' => route('directory.index', absolute: false)],
                ],
                'items' => [],
                'meta' => [
                    'mode' => 'deterministic_public_directory',
                    'llm_used' => false,
                    'privacy_scope' => 'public_directory_only',
                ],
            ];
        }

        return [
            'answer' => $this->answerForItems($items, $budget),
            'suggestions' => $items->map(fn (array $item): array => [
                'label' => 'Ver '.$item['business_name'],
                'href' => $item['business_url'],
            ])->unique('href')->values()->all(),
            'items' => $items->all(),
            'meta' => [
                'mode' => 'deterministic_public_directory',
                'llm_used' => false,
                'privacy_scope' => 'public_directory_only',
            ],
        ];
    }

    private function normalizeTerm(string $message): string
    {
        $message = Str::of($message)
            ->lower()
            ->replaceMatches('/\b(que|puedo|comprar|con|por|para|de|en|lempiras|lps|hnl|pesos|l\.?)\b/u', ' ')
            ->replaceMatches('/\d+/u', ' ')
            ->replaceMatches('/[^[:alnum:]\s]/u', ' ')
            ->squish()
            ->toString();

        return mb_substr($message, 0, 80);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function publicProducts(string $term, ?float $budget, ?string $city, ?string $category): Collection
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('tenants')) {
            return collect();
        }

        $query = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with([$this->tenantRelationSelect()])
            ->select($this->productColumns(['id', 'tenant_id', 'name', 'description', 'price', 'is_visible', 'status']))
            ->whereHas('tenant', fn (Builder $tenant): Builder => $this->applyPublicTenantFilters($tenant, $city, $category));

        if (Schema::hasColumn('products', 'is_visible')) {
            $query->where('is_visible', true);
        }

        if (Schema::hasColumn('products', 'status')) {
            $query->where(function (Builder $status): void {
                $status->whereNull('status')
                    ->orWhereIn('status', ['active', 'approved', 'published']);
            });
        }

        if ($budget !== null && Schema::hasColumn('products', 'price')) {
            $query->where('price', '<=', $budget);
        }

        $this->applyTextSearch($query, 'products', $term);

        return $query
            ->orderBy('price')
            ->limit(8)
            ->get()
            ->map(fn (Product $product): array => [
                'type' => 'product',
                'name' => (string) $product->name,
                'description' => Str::limit((string) ($product->description ?? ''), 120),
                'price' => (float) $product->price,
                'price_label' => 'L. '.number_format((float) $product->price, 2),
                'business_name' => (string) ($product->tenant?->name ?? 'Negocio local'),
                'business_url' => $this->tenantUrl($product->tenant?->slug),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function publicServices(string $term, ?float $budget, ?string $city, ?string $category): Collection
    {
        if (! Schema::hasTable('services') || ! Schema::hasTable('tenants')) {
            return collect();
        }

        $query = Service::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with([$this->tenantRelationSelect()])
            ->select($this->serviceColumns(['id', 'tenant_id', 'name', 'description', 'price', 'is_active']))
            ->whereHas('tenant', fn (Builder $tenant): Builder => $this->applyPublicTenantFilters($tenant, $city, $category));

        if (Schema::hasColumn('services', 'is_active')) {
            $query->where('is_active', true);
        }

        if ($budget !== null && Schema::hasColumn('services', 'price')) {
            $query->where('price', '<=', $budget);
        }

        $this->applyTextSearch($query, 'services', $term);

        return $query
            ->orderBy('price')
            ->limit(8)
            ->get()
            ->map(fn (Service $service): array => [
                'type' => 'service',
                'name' => (string) $service->name,
                'description' => Str::limit((string) ($service->description ?? ''), 120),
                'price' => (float) $service->price,
                'price_label' => 'L. '.number_format((float) $service->price, 2),
                'business_name' => (string) ($service->tenant?->name ?? 'Negocio local'),
                'business_url' => $this->tenantUrl($service->tenant?->slug),
            ]);
    }

    private function applyPublicTenantFilters(Builder $query, ?string $city, ?string $category): Builder
    {
        if (Schema::hasColumn('tenants', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $query->whereIn('subscription_status', ['active', 'trial']);
        }

        if ($city && Schema::hasColumn('tenants', 'city_id') && Schema::hasTable('cities')) {
            $query->whereHas('city', fn (Builder $cityQuery): Builder => $cityQuery->where('slug', $city));
        }

        if ($category && Schema::hasColumn('tenants', 'category_id') && Schema::hasTable('categories')) {
            $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('slug', $category));
        }

        return $query;
    }

    private function applyTextSearch(Builder $query, string $table, string $term): void
    {
        if ($term === '') {
            return;
        }

        $columns = collect(['name', 'description'])
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values();

        if ($columns->isEmpty()) {
            return;
        }

        $query->where(function (Builder $inner) use ($columns, $term): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $inner->{$method}($column, 'like', '%'.$term.'%');
            }
        });
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function productColumns(array $columns): array
    {
        return collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn('products', $column))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function serviceColumns(array $columns): array
    {
        return collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn('services', $column))
            ->values()
            ->all();
    }

    private function tenantUrl(?string $slug): string
    {
        if (! $slug) {
            return route('directory.index', absolute: false);
        }

        return route('tenant.microsite.show', ['tenant' => $slug], false);
    }

    private function tenantRelationSelect(): string
    {
        $columns = collect(['id', 'name', 'slug', 'city_id', 'category_id'])
            ->filter(fn (string $column): bool => Schema::hasColumn('tenants', $column))
            ->values()
            ->all();

        return 'tenant:'.implode(',', $columns);
    }

    private function answerForItems(Collection $items, ?float $budget): string
    {
        $prefix = $budget !== null
            ? 'Con L. '.number_format($budget, 2).' encontre opciones publicas dentro de tu presupuesto.'
            : 'Encontre opciones publicas que pueden servirte.';

        $names = $items->take(3)
            ->map(fn (array $item): string => $item['name'].' en '.$item['business_name'].' ('.$item['price_label'].')')
            ->implode('; ');

        return $prefix.' Puedes revisar: '.$names.'.';
    }

    private function emptyAnswer(?float $budget, ?string $city, ?string $category): string
    {
        $parts = [];

        if ($budget !== null) {
            $parts[] = 'presupuesto L. '.number_format($budget, 2);
        }

        if ($city) {
            $parts[] = 'ciudad '.$city;
        }

        if ($category) {
            $parts[] = 'categoria '.$category;
        }

        $context = $parts === [] ? '' : ' con '.implode(', ', $parts);

        return 'No encontre una coincidencia publica suficiente'.$context.'. Prueba subir el presupuesto, quitar filtros o buscar por categoria.';
    }
}
