<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Domain\Directory\Contracts\DirectoryGuideReader;
use App\Enums\StockStatus;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Category;
use App\Models\City;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class EloquentDirectoryGuideReader implements DirectoryGuideReader
{
    /**
     * @var list<string>
     */
    private const PURCHASEABLE_STOCK_STATUSES = [
        StockStatus::InStock->value,
        StockStatus::LowStock->value,
        StockStatus::OnBackorder->value,
        StockStatus::PreOrder->value,
    ];

    public function searchPublishedTenants(
        ?string $search,
        ?string $citySlug,
        ?string $categorySlug,
        int $limit = 12
    ): array {
        $safeLimit = max(1, min($limit, 50));

        $tenants = $this->tenantQuery($search, $citySlug, $categorySlug)
            ->with(['city:id,name,slug', 'category:id,name,slug'])
            ->orderBy('name')
            ->limit($safeLimit)
            ->get();

        $payload = [];

        foreach ($tenants as $tenant) {
            $payload[] = $this->tenantPayload($tenant);
        }

        return $payload;
    }

    public function recommendByBudget(
        float $budget,
        ?string $citySlug,
        ?string $categorySlug,
        ?string $search = null,
        int $limit = 8
    ): array {
        $safeLimit = max(1, min($limit, 20));
        $safeBudget = max(0, $budget);

        if ($safeBudget <= 0) {
            return [];
        }

        $productsTable = (new Product)->getTable();
        $menuSectionsTable = (new MenuSection)->getTable();
        $tenantsTable = (new Tenant)->getTable();
        $hasStockStatus = Schema::hasColumn($productsTable, 'stock_status');
        $hasMenuSection = Schema::hasColumn($productsTable, 'menu_section_id') && Schema::hasTable($menuSectionsTable);
        $hasProductDescription = Schema::hasColumn($productsTable, 'description');
        $hasTenantDescription = Schema::hasColumn($tenantsTable, 'description');
        $hasTenantTags = Schema::hasColumn($tenantsTable, 'tags');

        $selectColumns = [
            'id',
            'tenant_id',
            'name',
            'slug',
            'price',
        ];

        if ($hasStockStatus) {
            $selectColumns[] = 'stock_status';
        }

        if ($hasMenuSection) {
            $selectColumns[] = 'menu_section_id';
        }

        $productsQuery = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->select($selectColumns)
            ->where('price', '<=', $safeBudget)
            ->whereHas('tenant', function (Builder $query) use ($citySlug, $categorySlug): void {
                $query->withoutGlobalScope(TenantScope::class);
                $this->applyTenantFilters($query, null, $citySlug, $categorySlug);
            })
            ->orderBy('price')
            ->orderBy('name')
            ->limit($safeLimit * 8);

        if ($hasStockStatus) {
            $productsQuery->whereIn('stock_status', self::PURCHASEABLE_STOCK_STATUSES);
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $productsQuery->where(function (Builder $query) use (
                $term,
                $hasMenuSection,
                $hasProductDescription,
                $hasTenantDescription,
                $hasTenantTags
            ): void {
                $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhereHas('tenant', function (Builder $tenantQuery) use ($term, $hasTenantDescription, $hasTenantTags): void {
                        $tenantQuery->withoutGlobalScope(TenantScope::class);
                        $tenantQuery->where('name', 'like', "%{$term}%");

                        if ($hasTenantDescription) {
                            $tenantQuery->orWhere('description', 'like', "%{$term}%");
                        }

                        if ($hasTenantTags) {
                            $tenantQuery->orWhere('tags', 'like', "%{$term}%");
                        }
                    });

                if ($hasProductDescription) {
                    $query->orWhere('description', 'like', "%{$term}%");
                }

                if ($hasMenuSection) {
                    $query->orWhereHas('menuSection', function (Builder $menuSectionQuery) use ($term): void {
                        $menuSectionQuery->withoutGlobalScope(TenantScope::class);
                        $menuSectionQuery->where('name', 'like', "%{$term}%");
                    });
                }
            });
        }

        $withRelations = [
            'tenant' => static function ($query): void {
                $query
                    ->withoutGlobalScope(TenantScope::class)
                    ->select(['id', 'name', 'slug', 'city_id', 'category_id']);
            },
            'tenant.city:id,name,slug',
            'tenant.category:id,name,slug',
        ];

        if ($hasMenuSection) {
            $withRelations['menuSection'] = static function ($query): void {
                $query
                    ->withoutGlobalScope(TenantScope::class)
                    ->select(['id', 'name']);
            };
        }

        try {
            $products = $productsQuery
                ->with($withRelations)
                ->get();
        } catch (\Throwable) {
            try {
                return $this->recommendByBudgetRaw(
                    budget: $safeBudget,
                    citySlug: $citySlug,
                    categorySlug: $categorySlug,
                    search: $search,
                    limit: $safeLimit,
                    hasStockStatus: $hasStockStatus,
                    hasMenuSection: $hasMenuSection,
                );
            } catch (\Throwable $rawException) {
                throw new RuntimeException('sql_recommendation_query_failed', 0, $rawException);
            }
        }

        /** @var array<string, list<Product>> $grouped */
        $grouped = [];

        foreach ($products as $product) {
            $grouped[(string) $product->tenant_id][] = $product;
        }

        $recommendations = [];

        foreach ($grouped as $tenantProducts) {
            $topProducts = array_slice($tenantProducts, 0, 3);

            if ($topProducts === []) {
                continue;
            }

            $prices = array_map(static fn (Product $product): float => (float) $product->price, $topProducts);
            $minPrice = min($prices);
            $maxPrice = max($prices);
            $tenant = $topProducts[0]->tenant;

            $productPayload = [];

            foreach ($topProducts as $product) {
                $menuSection = $hasMenuSection ? $product->menuSection : null;
                $menuSectionName = $menuSection instanceof MenuSection ? (string) $menuSection->name : null;
                $rawStockStatus = $hasStockStatus ? $product->getRawOriginal('stock_status') : null;

                $productPayload[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'stock_status' => is_string($rawStockStatus) ? $rawStockStatus : null,
                    'menu_section' => $menuSectionName,
                ];
            }

            $recommendations[] = [
                'tenant' => $this->tenantPayload($tenant instanceof Tenant ? $tenant : null),
                'reason' => $this->reasonForBudgetFit($safeBudget, $minPrice, count($topProducts)),
                'price_range' => [
                    'min' => round($minPrice, 2),
                    'max' => round($maxPrice, 2),
                ],
                'products' => $productPayload,
            ];
        }

        usort($recommendations, static function (array $left, array $right): int {
            return (float) data_get($left, 'price_range.min', INF) <=> (float) data_get($right, 'price_range.min', INF);
        });

        return array_slice($recommendations, 0, $safeLimit);
    }

    private function recommendByBudgetRaw(
        float $budget,
        ?string $citySlug,
        ?string $categorySlug,
        ?string $search,
        int $limit,
        bool $hasStockStatus,
        bool $hasMenuSection,
    ): array {
        $productsTable = (new Product)->getTable();
        $tenantsTable = (new Tenant)->getTable();
        $citiesTable = (new City)->getTable();
        $categoriesTable = (new Category)->getTable();

        $query = DB::table($productsTable.' as p')
            ->join($tenantsTable.' as t', 't.id', '=', 'p.tenant_id')
            ->leftJoin($citiesTable.' as c', 'c.id', '=', 't.city_id')
            ->leftJoin($categoriesTable.' as cat', 'cat.id', '=', 't.category_id')
            ->select([
                'p.id',
                'p.tenant_id',
                'p.name',
                'p.slug',
                'p.price',
                't.name as tenant_name',
                't.slug as tenant_slug',
                'c.name as city_name',
                'c.slug as city_slug',
                'cat.name as category_name',
                'cat.slug as category_slug',
            ])
            ->where('p.price', '<=', $budget)
            ->where('t.is_active', true)
            ->where('t.subscription_status', 'active')
            ->where('t.verification_status', 'verified')
            ->whereNull('t.publication_paused_at')
            ->orderBy('p.price')
            ->orderBy('p.name')
            ->limit($limit * 8);

        if ($hasStockStatus) {
            $query
                ->addSelect('p.stock_status')
                ->whereIn('p.stock_status', self::PURCHASEABLE_STOCK_STATUSES);
        }

        if ($hasMenuSection) {
            $query
                ->leftJoin((new MenuSection)->getTable().' as ms', 'ms.id', '=', 'p.menu_section_id')
                ->addSelect('ms.name as menu_section');
        }

        if (is_string($citySlug) && $citySlug !== '') {
            $query->where('c.slug', $citySlug);
        }

        if (is_string($categorySlug) && $categorySlug !== '') {
            $query->where('cat.slug', $categorySlug);
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $hasTenantDescription = Schema::hasColumn($tenantsTable, 'description');
            $hasTenantTags = Schema::hasColumn($tenantsTable, 'tags');
            $hasProductDescription = Schema::hasColumn($productsTable, 'description');

            $query->where(function ($innerQuery) use ($term, $hasTenantDescription, $hasTenantTags, $hasProductDescription, $hasMenuSection): void {
                $innerQuery
                    ->where('p.name', 'like', "%{$term}%")
                    ->orWhere('p.slug', 'like', "%{$term}%")
                    ->orWhere('t.name', 'like', "%{$term}%");

                if ($hasProductDescription) {
                    $innerQuery->orWhere('p.description', 'like', "%{$term}%");
                }

                if ($hasTenantDescription) {
                    $innerQuery->orWhere('t.description', 'like', "%{$term}%");
                }

                if ($hasTenantTags) {
                    $innerQuery->orWhere('t.tags', 'like', "%{$term}%");
                }

                if ($hasMenuSection) {
                    $innerQuery->orWhere('ms.name', 'like', "%{$term}%");
                }
            });
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        /** @var array<string, list<object>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row->tenant_id][] = $row;
        }

        $recommendations = [];

        foreach ($grouped as $tenantRows) {
            $topRows = array_slice($tenantRows, 0, 3);
            if ($topRows === []) {
                continue;
            }

            $prices = array_map(static fn (object $row): float => (float) $row->price, $topRows);
            $minPrice = min($prices);
            $maxPrice = max($prices);
            $first = $topRows[0];

            $products = [];
            foreach ($topRows as $row) {
                $products[] = [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'slug' => (string) $row->slug,
                    'price' => (float) $row->price,
                    'stock_status' => property_exists($row, 'stock_status') && is_string($row->stock_status) ? $row->stock_status : null,
                    'menu_section' => property_exists($row, 'menu_section') && is_string($row->menu_section) ? $row->menu_section : null,
                ];
            }

            $tenantSlug = is_string($first->tenant_slug ?? null) ? $first->tenant_slug : null;

            $recommendations[] = [
                'tenant' => [
                    'id' => $first->tenant_id,
                    'name' => is_string($first->tenant_name ?? null) ? $first->tenant_name : null,
                    'slug' => $tenantSlug,
                    'microsite_path' => $tenantSlug !== null ? '/t/'.$tenantSlug : null,
                    'city' => [
                        'name' => is_string($first->city_name ?? null) ? $first->city_name : null,
                        'slug' => is_string($first->city_slug ?? null) ? $first->city_slug : null,
                    ],
                    'category' => [
                        'name' => is_string($first->category_name ?? null) ? $first->category_name : null,
                        'slug' => is_string($first->category_slug ?? null) ? $first->category_slug : null,
                    ],
                ],
                'reason' => $this->reasonForBudgetFit($budget, $minPrice, count($topRows)),
                'price_range' => [
                    'min' => round($minPrice, 2),
                    'max' => round($maxPrice, 2),
                ],
                'products' => $products,
            ];
        }

        $priceSortKeys = [];
        foreach ($recommendations as $index => $recommendation) {
            $priceSortKeys[$index] = (float) data_get($recommendation, 'price_range.min', INF);
        }

        if ($priceSortKeys !== []) {
            array_multisort($priceSortKeys, SORT_ASC, $recommendations);
        }

        return array_slice($recommendations, 0, $limit);
    }

    public function rerankRecommendations(
        array $recommendations,
        ?string $search,
        int $limit = 8
    ): array {
        $query = $this->normalizeText($search);
        $safeLimit = max(1, min($limit, 20));
        $safeRecommendations = $this->ensureTenantDiversity($recommendations);

        if ($query === '') {
            return array_slice($safeRecommendations, 0, $safeLimit);
        }

        $queryEmbedding = $this->fetchEmbeddings([$query])[0] ?? throw new RuntimeException('embedding_not_found');

        $similarityByRecommendation = [];
        $storedEmbeddingIssue = null;

        try {
            $similarityByRecommendation = $this->similarityFromStoredEmbeddings($safeRecommendations, $queryEmbedding);
        } catch (RuntimeException $exception) {
            $storedEmbeddingIssue = $exception->getMessage();
        }

        if ($similarityByRecommendation === []) {
            $similarityByRecommendation = $this->similarityFromOnTheFlyEmbeddings($safeRecommendations, $queryEmbedding);
        }

        if ($similarityByRecommendation === []) {
            if (is_string($storedEmbeddingIssue) && $storedEmbeddingIssue !== '') {
                throw new RuntimeException($storedEmbeddingIssue);
            }

            throw new RuntimeException('embedding_not_found');
        }

        $weights = (array) config('directory.guide.weights', []);
        $vectorWeight = max(0.0, (float) ($weights['vector_similarity'] ?? 0.6));
        $lexicalWeight = max(0.0, (float) ($weights['lexical_match'] ?? 0.25));
        $priceWeight = max(0.0, (float) ($weights['price_fit'] ?? 0.15));

        $normalizedBudget = $this->inferBudgetFromRecommendations($safeRecommendations);

        usort($safeRecommendations, function (array $left, array $right) use (
            $query,
            $similarityByRecommendation,
            $vectorWeight,
            $lexicalWeight,
            $priceWeight,
            $normalizedBudget
        ): int {
            $leftKey = $this->recommendationKey($left);
            $rightKey = $this->recommendationKey($right);

            $leftVector = max(0.0, (float) ($similarityByRecommendation[$leftKey] ?? 0.0));
            $rightVector = max(0.0, (float) ($similarityByRecommendation[$rightKey] ?? 0.0));

            $leftLexical = $this->recommendationLexicalScore($left, $query);
            $rightLexical = $this->recommendationLexicalScore($right, $query);

            $leftPrice = $this->recommendationPriceFitScore($left, $normalizedBudget);
            $rightPrice = $this->recommendationPriceFitScore($right, $normalizedBudget);

            $leftScore = ($vectorWeight * $leftVector) + ($lexicalWeight * $leftLexical) + ($priceWeight * $leftPrice);
            $rightScore = ($vectorWeight * $rightVector) + ($lexicalWeight * $rightLexical) + ($priceWeight * $rightPrice);

            if ($leftScore === $rightScore) {
                $leftMinPrice = (float) data_get($left, 'price_range.min', INF);
                $rightMinPrice = (float) data_get($right, 'price_range.min', INF);

                return $leftMinPrice <=> $rightMinPrice;
            }

            return $rightScore <=> $leftScore;
        });

        return array_slice($safeRecommendations, 0, $safeLimit);
    }

    /**
     * @return Builder<Tenant>
     */
    private function tenantQuery(
        ?string $search,
        ?string $citySlug,
        ?string $categorySlug,
    ): Builder {
        $builder = Tenant::query();

        $this->applyTenantFilters($builder, $search, $citySlug, $categorySlug);

        return $builder;
    }

    private function applyTenantFilters(
        Builder $query,
        ?string $search,
        ?string $citySlug,
        ?string $categorySlug
    ): void {
        $query
            ->where('is_active', true)
            ->where('subscription_status', 'active')
            ->where('verification_status', 'verified')
            ->whereNull('publication_paused_at');

        $term = trim((string) $search);

        if ($term !== '') {
            $query->where(function (Builder $innerQuery) use ($term): void {
                $innerQuery
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('tags', 'like', "%{$term}%");
            });
        }

        if (is_string($citySlug) && $citySlug !== '') {
            $query->whereHas('city', function (Builder $cityQuery) use ($citySlug): void {
                $cityQuery->where('slug', $citySlug);
            });
        }

        if (is_string($categorySlug) && $categorySlug !== '') {
            $query->whereHas('category', function (Builder $categoryQuery) use ($categorySlug): void {
                $categoryQuery->where('slug', $categorySlug);
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantPayload(?Tenant $tenant): array
    {
        if (! $tenant instanceof Tenant) {
            return [
                'id' => null,
                'name' => null,
                'slug' => null,
                'microsite_path' => null,
                'city' => null,
                'category' => null,
            ];
        }

        $city = $tenant->city;
        $category = $tenant->category;

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'microsite_path' => '/t/'.$tenant->slug,
            'city' => [
                'name' => $city instanceof City ? $city->name : null,
                'slug' => $city instanceof City ? $city->slug : null,
            ],
            'category' => [
                'name' => $category instanceof Category ? $category->name : null,
                'slug' => $category instanceof Category ? $category->slug : null,
            ],
        ];
    }

    private function reasonForBudgetFit(float $budget, float $minPrice, int $options): string
    {
        if ($options >= 3 && $minPrice <= $budget * 0.6) {
            return 'Tienes varias opciones comodas dentro de tu presupuesto.';
        }

        if ($minPrice <= $budget * 0.9) {
            return 'Hay opciones ajustadas a tu presupuesto actual.';
        }

        return 'Encontramos opciones disponibles dentro del limite que indicaste.';
    }

    /**
     * @param  list<float>  $queryEmbedding
     * @return array<string, float>
     */
    private function similarityFromStoredEmbeddings(array $recommendations, array $queryEmbedding): array
    {
        $pgvectorEnabled = (bool) config('directory.guide.pgvector.enabled', false);
        $table = (string) config('directory.guide.pgvector.table', 'directory_product_embeddings');

        if (! $pgvectorEnabled || ! Schema::hasTable($table)) {
            return [];
        }

        $recommendationProducts = collect($recommendations)
            ->mapWithKeys(fn (array $recommendation): array => [
                $this->recommendationKey($recommendation) => collect((array) data_get($recommendation, 'products', []))
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->filter(static fn (array $productIds): bool => $productIds !== []);

        if ($recommendationProducts->isEmpty()) {
            return [];
        }

        $allProductIds = $recommendationProducts
            ->flatten()
            ->unique()
            ->values()
            ->all();

        if ($allProductIds === []) {
            return [];
        }

        $driver = DB::getDriverName();
        $productSimilarities = [];
        $freshEmbeddingsFound = false;
        $staleThresholdMinutes = max(1, (int) config('directory.guide.embedding_stale_minutes', 1440));
        $staleCutoff = now()->subMinutes($staleThresholdMinutes);
        $storedRowsCount = 0;

        if ($driver === 'pgsql' && Schema::hasColumn($table, 'embedding')) {
            $vectorLiteral = $this->toVectorLiteral($queryEmbedding);

            $rows = DB::table($table)
                ->select('product_id', 'updated_at')
                ->selectRaw('1 - (embedding <=> CAST(? AS vector)) AS similarity', [$vectorLiteral])
                ->whereIn('product_id', $allProductIds)
                ->whereNotNull('embedding')
                ->get();

            $storedRowsCount = $rows->count();

            foreach ($rows as $row) {
                $productSimilarities[(int) $row->product_id] = (float) ($row->similarity ?? 0.0);

                if ($this->isEmbeddingRowFresh($row->updated_at ?? null, $staleCutoff)) {
                    $freshEmbeddingsFound = true;
                }
            }
        } elseif (Schema::hasColumn($table, 'embedding_json')) {
            $rows = DB::table($table)
                ->select('product_id', 'embedding_json', 'updated_at')
                ->whereIn('product_id', $allProductIds)
                ->whereNotNull('embedding_json')
                ->get();

            $storedRowsCount = $rows->count();

            foreach ($rows as $row) {
                $vector = json_decode((string) $row->embedding_json, true);
                if (! is_array($vector) || $vector === []) {
                    continue;
                }

                $normalized = array_map(static fn (mixed $value): float => (float) $value, $vector);
                $productSimilarities[(int) $row->product_id] = $this->cosineSimilarity($queryEmbedding, $normalized);

                if ($this->isEmbeddingRowFresh($row->updated_at ?? null, $staleCutoff)) {
                    $freshEmbeddingsFound = true;
                }
            }
        }

        if ($storedRowsCount === 0) {
            throw new RuntimeException('embedding_not_found');
        }

        if (! $freshEmbeddingsFound) {
            throw new RuntimeException('embedding_sync_stale');
        }

        if ($productSimilarities === []) {
            throw new RuntimeException('embedding_not_found');
        }

        $result = [];

        foreach ($recommendationProducts as $recommendationKey => $productIds) {
            $scores = collect($productIds)
                ->map(static fn (int $productId): ?float => $productSimilarities[$productId] ?? null)
                ->filter(static fn (?float $score): bool => $score !== null)
                ->values();

            if ($scores->isEmpty()) {
                continue;
            }

            $result[$recommendationKey] = (float) $scores->max();
        }

        return $result;
    }

    /**
     * @param  list<float>  $queryEmbedding
     * @return array<string, float>
     */
    private function similarityFromOnTheFlyEmbeddings(array $recommendations, array $queryEmbedding): array
    {
        $texts = [];
        $keys = [];

        foreach ($recommendations as $recommendation) {
            $text = $this->recommendationEmbeddingText($recommendation);

            if ($text === '') {
                continue;
            }

            $keys[] = $this->recommendationKey($recommendation);
            $texts[] = $text;
        }

        if ($texts === []) {
            return [];
        }

        $candidateEmbeddings = $this->fetchEmbeddings($texts);

        $scores = [];

        foreach ($candidateEmbeddings as $index => $embedding) {
            $recommendationKey = $keys[$index] ?? null;

            if (! is_string($recommendationKey) || $recommendationKey === '') {
                continue;
            }

            $scores[$recommendationKey] = $this->cosineSimilarity($queryEmbedding, $embedding);
        }

        return $scores;
    }

    /**
     * @param  list<string>  $inputs
     * @return list<list<float>>
     */
    private function fetchEmbeddings(array $inputs): array
    {
        $model = (string) config('directory.guide.embedding_model', 'text-embedding-3-small');
        $ttl = max(30, (int) config('directory.guide.embedding_cache_ttl_seconds', 300));
        $resolved = [];
        $missing = [];

        foreach ($inputs as $index => $input) {
            $normalizedInput = $this->normalizedEmbeddingInput($input);
            $cacheKey = $this->embeddingCacheKey($model, $normalizedInput);
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && $cached !== []) {
                $resolved[$index] = array_map(static fn (mixed $value): float => (float) $value, $cached);

                continue;
            }

            $missing[$index] = $normalizedInput;
        }

        if ($missing !== []) {
            $fresh = $this->fetchEmbeddingsFromProvider(array_values($missing), $model);

            foreach (array_keys($missing) as $position => $originalIndex) {
                $embedding = $fresh[$position] ?? null;

                if (! is_array($embedding) || $embedding === []) {
                    continue;
                }

                $resolved[$originalIndex] = $embedding;
                $cacheKey = $this->embeddingCacheKey($model, $missing[$originalIndex]);
                Cache::put($cacheKey, $embedding, now()->addSeconds($ttl));
            }
        }

        ksort($resolved);

        if (count($resolved) !== count($inputs)) {
            throw new RuntimeException('embedding_not_found');
        }

        return array_values($resolved);
    }

    /**
     * @param  list<string>  $inputs
     * @return list<list<float>>
     */
    private function fetchEmbeddingsFromProvider(array $inputs, string $model): array
    {
        $apiKey = (string) config('services.openai.api_key', '');

        if (trim($apiKey) === '') {
            throw new RuntimeException('openai_auth_failed');
        }

        if ($this->isOpenAiCircuitOpen()) {
            throw new RuntimeException('openai_timeout');
        }

        $timeout = max(3, (int) config('directory.guide.openai_timeout_seconds', 8));
        $maxRetries = max(0, (int) config('directory.guide.openai_max_retries', 1));
        $attempts = max(1, $maxRetries + 1);
        $lastFailure = 'openai_timeout';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->post('https://api.openai.com/v1/embeddings', [
                        'model' => $model,
                        'input' => $inputs,
                    ]);
            } catch (\Throwable) {
                $this->registerOpenAiCircuitFailure();

                if ($attempt < $attempts) {
                    usleep($attempt * 150000);

                    continue;
                }

                throw new RuntimeException('openai_timeout');
            }

            $status = $response->status();

            if ($status === 401 || $status === 403) {
                $this->registerOpenAiCircuitFailure();
                throw new RuntimeException('openai_auth_failed');
            }

            if ($response->ok()) {
                $data = $response->json('data');

                if (! is_array($data) || $data === []) {
                    $this->registerOpenAiCircuitFailure();
                    throw new RuntimeException('embedding_not_found');
                }

                $byIndex = [];

                foreach ($data as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $index = (int) ($item['index'] ?? count($byIndex));
                    $embedding = $item['embedding'] ?? null;

                    if (! is_array($embedding) || $embedding === []) {
                        continue;
                    }

                    $byIndex[$index] = array_map(static fn (mixed $value): float => (float) $value, $embedding);
                }

                ksort($byIndex);

                if (count($byIndex) !== count($inputs)) {
                    $this->registerOpenAiCircuitFailure();
                    throw new RuntimeException('embedding_not_found');
                }

                $this->resetOpenAiCircuitFailures();

                return array_values($byIndex);
            }

            $this->registerOpenAiCircuitFailure();
            $lastFailure = 'openai_timeout';

            if ($attempt < $attempts) {
                usleep($attempt * 150000);

                continue;
            }
        }

        throw new RuntimeException($lastFailure);
    }

    private function normalizedEmbeddingInput(string $input): string
    {
        $normalized = $this->normalizeText($input);

        if ($normalized !== '') {
            return $normalized;
        }

        return trim($input);
    }

    private function embeddingCacheKey(string $model, string $input): string
    {
        return 'directory:guide:embedding:'.hash('sha256', $model.'|'.$input);
    }

    private function openAiCircuitKey(): string
    {
        return 'directory:guide:openai:circuit-breaker';
    }

    private function isOpenAiCircuitOpen(): bool
    {
        $state = Cache::get($this->openAiCircuitKey(), [
            'failures' => 0,
            'opened_at' => null,
        ]);

        if (! is_array($state)) {
            return false;
        }

        $threshold = max(1, (int) config('directory.guide.circuit_breaker.failure_threshold', 3));
        $cooldownSeconds = max(5, (int) config('directory.guide.circuit_breaker.cooldown_seconds', 60));
        $failures = (int) ($state['failures'] ?? 0);
        $openedAt = $state['opened_at'] ?? null;

        if ($failures < $threshold || ! is_string($openedAt) || $openedAt === '') {
            return false;
        }

        return now()->diffInSeconds($openedAt) < $cooldownSeconds;
    }

    private function registerOpenAiCircuitFailure(): void
    {
        $key = $this->openAiCircuitKey();
        $state = Cache::get($key, [
            'failures' => 0,
            'opened_at' => null,
        ]);

        if (! is_array($state)) {
            $state = [
                'failures' => 0,
                'opened_at' => null,
            ];
        }

        $threshold = max(1, (int) config('directory.guide.circuit_breaker.failure_threshold', 3));
        $cooldownSeconds = max(5, (int) config('directory.guide.circuit_breaker.cooldown_seconds', 60));

        $state['failures'] = ((int) ($state['failures'] ?? 0)) + 1;

        if ((int) $state['failures'] >= $threshold) {
            $state['opened_at'] = now()->toIso8601String();
        }

        Cache::put($key, $state, now()->addSeconds($cooldownSeconds * 5));
    }

    private function resetOpenAiCircuitFailures(): void
    {
        Cache::forget($this->openAiCircuitKey());
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        $dimension = min(count($left), count($right));

        if ($dimension === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        for ($i = 0; $i < $dimension; $i++) {
            $l = (float) $left[$i];
            $r = (float) $right[$i];

            $dot += $l * $r;
            $leftMagnitude += $l * $l;
            $rightMagnitude += $r * $r;
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }

    private function recommendationEmbeddingText(array $recommendation): string
    {
        $tenantName = (string) data_get($recommendation, 'tenant.name', '');
        $category = (string) data_get($recommendation, 'tenant.category.name', '');
        $city = (string) data_get($recommendation, 'tenant.city.name', '');
        $reason = (string) data_get($recommendation, 'reason', '');

        $products = collect((array) data_get($recommendation, 'products', []))
            ->map(function (array $product): string {
                $name = (string) ($product['name'] ?? '');
                $section = (string) ($product['menu_section'] ?? '');
                $price = isset($product['price']) ? (string) $product['price'] : '';

                return trim("{$name} {$section} {$price}");
            })
            ->implode(' | ');

        return trim("{$tenantName}. {$category}. {$city}. {$reason}. {$products}");
    }

    private function recommendationLexicalScore(array $recommendation, string $query): float
    {
        if ($query === '') {
            return 0.0;
        }

        $score = 0.0;

        $tenantName = $this->normalizeText((string) data_get($recommendation, 'tenant.name', ''));
        if ($tenantName !== '' && str_contains($tenantName, $query)) {
            $score += 0.45;
        }

        $productNames = collect((array) data_get($recommendation, 'products', []))
            ->pluck('name')
            ->filter(static fn ($name): bool => is_string($name))
            ->map(fn (string $name): string => $this->normalizeText($name));

        $productMatches = $productNames
            ->filter(static fn (string $name): bool => str_contains($name, $query))
            ->count();

        if ($productMatches > 0) {
            $score += min(0.40, $productMatches * 0.20);
        }

        $categoryName = $this->normalizeText((string) data_get($recommendation, 'tenant.category.name', ''));
        if ($categoryName !== '' && str_contains($categoryName, $query)) {
            $score += 0.15;
        }

        return min(1.0, max(0.0, $score));
    }

    private function recommendationPriceFitScore(array $recommendation, float $budget): float
    {
        if ($budget <= 0.0) {
            return 0.0;
        }

        $minPrice = (float) data_get($recommendation, 'price_range.min', 0.0);

        if ($minPrice <= 0.0 || $minPrice > $budget) {
            return 0.0;
        }

        return min(1.0, $minPrice / $budget);
    }

    private function inferBudgetFromRecommendations(array $recommendations): float
    {
        $maxPrice = collect($recommendations)
            ->map(static fn (array $recommendation): float => (float) data_get($recommendation, 'price_range.max', 0.0))
            ->max();

        return is_numeric($maxPrice) ? (float) $maxPrice : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $recommendations
     * @return list<array<string, mixed>>
     */
    private function ensureTenantDiversity(array $recommendations): array
    {
        $seenTenants = [];
        $diverse = [];

        foreach ($recommendations as $recommendation) {
            $tenantId = (string) data_get($recommendation, 'tenant.id', data_get($recommendation, 'tenant.slug', ''));

            if ($tenantId === '' || isset($seenTenants[$tenantId])) {
                continue;
            }

            $seenTenants[$tenantId] = true;
            $diverse[] = $recommendation;
        }

        return $diverse;
    }

    private function isEmbeddingRowFresh(mixed $updatedAt, \DateTimeInterface $staleCutoff): bool
    {
        if ($updatedAt === null) {
            return false;
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $updatedAt)->greaterThanOrEqualTo($staleCutoff);
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeText(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return is_string($normalized) ? $normalized : '';
    }

    private function recommendationKey(array $recommendation): string
    {
        $tenantId = (string) data_get($recommendation, 'tenant.id', data_get($recommendation, 'tenant.slug', 'unknown-tenant'));

        return $tenantId.':'.md5((string) json_encode(data_get($recommendation, 'products', [])));
    }

    /**
     * @param  list<float>  $embedding
     */
    private function toVectorLiteral(array $embedding): string
    {
        return '['.implode(',', array_map(static fn (float $value): string => sprintf('%.8F', $value), $embedding)).']';
    }
}
