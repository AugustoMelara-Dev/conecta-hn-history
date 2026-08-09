<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
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
    public function answer(
        string $message,
        ?float $budget = null,
        ?string $city = null,
        ?string $category = null,
        array $semanticCandidates = [],
    ): array {
        $term = $this->normalizeTerm($message);
        $products = $this->publicProducts(
            $term,
            $budget,
            $city,
            $category,
            $this->candidateIds($semanticCandidates, 'product'),
        );
        $services = $this->publicServices(
            $term,
            $budget,
            $city,
            $category,
            $this->candidateIds($semanticCandidates, 'service'),
        );

        $items = collect($products->all())
            ->merge($services->all())
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
                'source' => 'public_directory',
                'fallback' => true,
                'meta' => [
                    'mode' => 'deterministic_public_directory',
                    'llm_used' => false,
                    'privacy_scope' => 'public_directory_only',
                    'source' => 'public_directory',
                    'fallback' => true,
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
            'source' => 'public_directory',
            'fallback' => true,
            'meta' => [
                'mode' => 'deterministic_public_directory',
                'llm_used' => false,
                'privacy_scope' => 'public_directory_only',
                'source' => 'public_directory',
                'fallback' => true,
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $candidates
     * @return list<string>
     */
    private function candidateIds(array $candidates, string $resourceType): array
    {
        return collect($candidates)
            ->filter(static fn (mixed $candidate): bool => is_array($candidate)
                && ($candidate['resource_type'] ?? null) === $resourceType
                && is_string($candidate['resource_id'] ?? null)
                && ctype_digit($candidate['resource_id']))
            ->pluck('resource_id')
            ->unique()
            ->take(20)
            ->values()
            ->all();
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
    private function publicProducts(string $term, ?float $budget, ?string $city, ?string $category, array $candidateIds): Collection
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('tenants')) {
            return collect();
        }

        $query = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with([$this->tenantRelationSelect()])
            ->select($this->productColumns(['id', 'tenant_id', 'name', 'description', 'price', 'is_active', 'is_visible', 'status', 'manage_stock', 'stock_quantity', 'allow_backorders', 'stock_status']))
            ->whereHas('tenant', fn (Builder $tenant): Builder => $this->applyPublicTenantFilters($tenant, $city, $category));

        if (Schema::hasColumn('products', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('products', 'is_visible')) {
            $query->where('is_visible', true);
        }

        if (Schema::hasColumn('products', 'manage_stock')) {
            $hasQuantity = Schema::hasColumn('products', 'stock_quantity');
            $hasBackorders = Schema::hasColumn('products', 'allow_backorders');
            $query->where(function (Builder $stock) use ($hasQuantity, $hasBackorders): void {
                $stock->where('manage_stock', false);

                if ($hasQuantity) {
                    $stock->orWhere('stock_quantity', '>', 0);
                }

                if ($hasBackorders) {
                    $stock->orWhere('allow_backorders', true);
                }
            });
        }

        if (Schema::hasColumn('products', 'status')) {
            $query->where(function (Builder $status): void {
                $status->whereIn('status', ['active', 'approved', 'published']);
            });
        }

        if ($budget !== null && Schema::hasColumn('products', 'price')) {
            $query->where('price', '<=', $budget);
        }

        $this->applyCandidateSearch($query, 'products', $term, $candidateIds);

        /** @var Collection<int, array<string, mixed>> $items */
        $items = $query
            ->orderBy('price')
            ->limit(8)
            ->get()
            ->map(function (Product $product): array {
                $tenant = $product->tenant;
                $businessName = $tenant instanceof Tenant ? (string) $tenant->name : 'Negocio local';
                $businessUrl = $this->tenantUrl($tenant instanceof Tenant ? (string) $tenant->slug : null);

                return [
                    'type' => 'product',
                    'evidence_id' => 'product:'.$product->getKey(),
                    'name' => (string) $product->name,
                    'description' => Str::limit(strip_tags((string) ($product->description ?? '')), 120),
                    'price' => (float) $product->price,
                    'price_label' => 'L. '.number_format((float) $product->price, 2),
                    'business_name' => $businessName,
                    'business_url' => $businessUrl,
                    'url' => $businessUrl,
                    'availability' => (bool) ($product->manage_stock ?? false)
                        && (int) ($product->stock_quantity ?? 0) <= 0
                        ? 'backorder'
                        : 'available',
                ];
            });

        return $items;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function publicServices(string $term, ?float $budget, ?string $city, ?string $category, array $candidateIds): Collection
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

        $this->applyCandidateSearch($query, 'services', $term, $candidateIds);

        /** @var Collection<int, array<string, mixed>> $items */
        $items = $query
            ->orderBy('price')
            ->limit(8)
            ->get()
            ->map(function (Service $service): array {
                $tenant = $service->tenant;
                $businessName = $tenant instanceof Tenant ? (string) $tenant->name : 'Negocio local';
                $businessUrl = $this->tenantUrl($tenant instanceof Tenant ? (string) $tenant->slug : null);

                return [
                    'type' => 'service',
                    'evidence_id' => 'service:'.$service->getKey(),
                    'name' => (string) $service->name,
                    'description' => Str::limit(strip_tags((string) ($service->description ?? '')), 120),
                    'price' => (float) $service->price,
                    'price_label' => 'L. '.number_format((float) $service->price, 2),
                    'business_name' => $businessName,
                    'business_url' => $businessUrl,
                    'url' => $businessUrl,
                    'availability' => 'available',
                ];
            });

        return $items;
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

    /** @param list<string> $candidateIds */
    private function applyCandidateSearch(Builder $query, string $table, string $term, array $candidateIds): void
    {
        if ($candidateIds === []) {
            $this->applyTextSearch($query, $table, $term);

            return;
        }

        $query->where(function (Builder $candidate) use ($table, $term, $candidateIds): void {
            if ($term !== '') {
                $this->applyTextSearch($candidate, $table, $term);
                $candidate->orWhereIn('id', $candidateIds);

                return;
            }

            $candidate->whereIn('id', $candidateIds);
        });
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

        return route('tenant.public.show', ['tenant' => $slug], false);
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
