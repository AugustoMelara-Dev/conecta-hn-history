<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Shared\Services\TenantContext;
use App\Enums\StockStatus;
use App\Infrastructure\Directory\QdrantClient;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Category;
use App\Models\City;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class SyncDirectoryEmbeddingsCommand extends Command
{
    protected $signature = 'directory:sync-embeddings
        {--tenant= : Sync embeddings only for a specific tenant UUID}
        {--since-hours=0 : Sync only records updated in the last N hours (0 = all)}
        {--limit=0 : Max products to process (0 = all)}
        {--chunk=100 : Chunk size for query iteration}
        {--force : Rebuild embeddings even when hash is unchanged}';

    protected $description = 'Sync OpenAI embeddings for published directory products (pgvector-ready, read-only guidance).';

    /**
     * @var list<string>
     */
    private const PURCHASEABLE_STOCK_STATUSES = [
        StockStatus::InStock->value,
        StockStatus::LowStock->value,
        StockStatus::OnBackorder->value,
        StockStatus::PreOrder->value,
    ];

    public function handle(): int
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);
        if (! $tenantContext->isInitialized()) {
            $tenantContext->setAsSystemRoot(isConsoleOrTesting: true);
        }

        $apiKey = (string) config('services.openai.api_key', '');

        if (trim($apiKey) === '') {
            $this->error('OPENAI_API_KEY is missing. Aborting directory embedding sync.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('directory_product_embeddings')) {
            $this->error('Table directory_product_embeddings not found. Run migrations first.');

            return self::FAILURE;
        }

        $tenantFilter = $this->option('tenant');
        $sinceHours = max(0, (int) $this->option('since-hours'));
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(10, min((int) $this->option('chunk'), 500));
        $force = (bool) $this->option('force');
        $qdrantClient = app(QdrantClient::class);

        $query = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->select(['id', 'tenant_id', 'name', 'slug', 'price', 'description', 'stock_status', 'menu_section_id'])
            ->with(['tenant:id,name,slug,city_id,category_id', 'tenant.city:id,name,slug', 'tenant.category:id,name,slug', 'menuSection:id,name'])
            ->whereIn('stock_status', self::PURCHASEABLE_STOCK_STATUSES)
            ->whereHas('tenant', static function ($tenantQuery): void {
                $tenantQuery
                    ->where('status', 'approved')
                    ->where('is_active', true)
                    ->whereIn('subscription_status', ['active', 'trial']);
            })
            ->where('is_visible', true)
            ->orderBy('id');

        if (is_string($tenantFilter) && $tenantFilter !== '') {
            $query->where('tenant_id', $tenantFilter);
        }

        if ($sinceHours > 0) {
            $since = now()->subHours($sinceHours);

            $query->where(function ($innerQuery) use ($since): void {
                $innerQuery
                    ->where('updated_at', '>=', $since)
                    ->orWhereHas('tenant', static function ($tenantQuery) use ($since): void {
                        $tenantQuery->where('updated_at', '>=', $since);
                    })
                    ->orWhereHas('menuSection', static function ($menuSectionQuery) use ($since): void {
                        $menuSectionQuery->where('updated_at', '>=', $since);
                    });
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $model = (string) config('directory.guide.embedding_model', 'text-embedding-3-small');
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $qdrantUpserted = 0;

        $query->chunkById($chunk, function ($products) use ($model, $force, $qdrantClient, &$processed, &$updated, &$skipped, &$qdrantUpserted): void {
            foreach ($products as $product) {
                $processed++;

                $input = $this->embeddingInputForProduct($product);

                if ($input === '') {
                    $skipped++;

                    continue;
                }

                $inputHash = hash('sha256', $input);

                $existing = DB::table('directory_product_embeddings')
                    ->where('product_id', $product->id)
                    ->where('embedding_model', $model)
                    ->first();

                if (! $force && $existing !== null && (string) ($existing->embedding_hash ?? '') === $inputHash) {
                    $skipped++;

                    continue;
                }

                $embedding = $this->fetchEmbedding($input, $model);
                $embeddingJson = json_encode($embedding, JSON_THROW_ON_ERROR);

                $id = $existing !== null && isset($existing->id)
                    ? (string) $existing->id
                    : (string) Str::uuid();
                $createdAt = $existing !== null && isset($existing->created_at)
                    ? $existing->created_at
                    : now();

                DB::table('directory_product_embeddings')->updateOrInsert(
                    [
                        'product_id' => $product->id,
                        'embedding_model' => $model,
                    ],
                    [
                        'id' => $id,
                        'tenant_id' => (string) $product->tenant_id,
                        'embedding_hash' => $inputHash,
                        'embedding_input' => $input,
                        'embedding_json' => $embeddingJson,
                        'updated_at' => now(),
                        'created_at' => $createdAt,
                    ]
                );

                $this->syncPgvectorColumnIfAvailable($id, $embedding);
                $qdrantSynced = $this->syncQdrantPoint(
                    qdrantClient: $qdrantClient,
                    product: $product,
                    embedding: $embedding,
                    model: $model,
                    inputHash: $inputHash,
                );

                $updated++;
                if ($qdrantSynced) {
                    $qdrantUpserted++;
                }
            }
        });

        $pruned = $this->pruneStaleEmbeddings(
            qdrantClient: $qdrantClient,
            tenantFilter: is_string($tenantFilter) ? $tenantFilter : null,
            sinceHours: $sinceHours,
            model: $model,
        );

        $this->info(sprintf(
            'Directory embeddings sync completed. processed=%d updated=%d skipped=%d qdrant_upserted=%d pruned=%d since_hours=%d',
            $processed,
            $updated,
            $skipped,
            $qdrantUpserted,
            $pruned,
            $sinceHours
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<float>
     */
    private function fetchEmbedding(string $input, string $model): array
    {
        $timeout = max(3, (int) config('directory.guide.openai_timeout_seconds', 8));

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->acceptJson()
            ->timeout($timeout)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => [$input],
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException('OpenAI embeddings request failed with status '.$response->status());
        }

        $payload = $response->json('data.0.embedding');

        if (! is_array($payload) || $payload === []) {
            throw new \RuntimeException('OpenAI embeddings payload is empty or malformed.');
        }

        return array_map(static fn (mixed $value): float => (float) $value, $payload);
    }

    private function embeddingInputForProduct(Product $product): string
    {
        $tenant = $product->tenant;
        $tenantName = '';
        $categoryName = '';
        $cityName = '';

        if ($tenant instanceof Tenant) {
            $tenantName = (string) $tenant->name;

            $category = $tenant->category;
            if ($category instanceof Category) {
                $categoryName = (string) $category->name;
            }

            $city = $tenant->city;
            if ($city instanceof City) {
                $cityName = (string) $city->name;
            }
        }

        $menuSectionName = '';
        $menuSection = $product->menuSection;
        if ($menuSection instanceof MenuSection) {
            $menuSectionName = (string) $menuSection->name;
        }

        $parts = [
            $tenantName,
            $categoryName,
            $cityName,
            (string) $product->name,
            $menuSectionName,
            'precio '.number_format((float) $product->price, 2, '.', ''),
            (string) ($product->description ?? ''),
        ];

        $input = trim(implode('. ', array_filter($parts, static fn (string $value): bool => $value !== '')));

        return mb_substr($input, 0, 2000);
    }

    /**
     * @param  list<float>  $embedding
     */
    private function syncQdrantPoint(
        QdrantClient $qdrantClient,
        Product $product,
        array $embedding,
        string $model,
        string $inputHash,
    ): bool {
        if (! $qdrantClient->enabled()) {
            return false;
        }

        $tenant = $product->tenant;
        $tenantSlug = $tenant instanceof Tenant ? (string) ($tenant->slug ?? '') : '';
        $tenantName = $tenant instanceof Tenant ? (string) ($tenant->name ?? '') : '';
        $citySlug = $tenant instanceof Tenant && $tenant->city instanceof City ? (string) ($tenant->city->slug ?? '') : '';
        $categorySlug = $tenant instanceof Tenant && $tenant->category instanceof Category ? (string) ($tenant->category->slug ?? '') : '';
        $stockStatus = $product->stock_status->value;
        $published = $tenant instanceof Tenant
            && (string) $tenant->status === 'approved'
            && (bool) $tenant->is_active
            && in_array((string) $tenant->subscription_status, ['active', 'trial'], true)
            && (bool) $product->is_visible
            && in_array($stockStatus, self::PURCHASEABLE_STOCK_STATUSES, true);

        try {
            $qdrantClient->upsertPoints([
                [
                    'id' => (int) $product->id,
                    'vector' => $embedding,
                    'payload' => [
                        'product_id' => (int) $product->id,
                        'tenant_id' => (string) $product->tenant_id,
                        'tenant_slug' => $tenantSlug,
                        'tenant_name' => $tenantName,
                        'city_slug' => $citySlug,
                        'category_slug' => $categorySlug,
                        'product_slug' => (string) ($product->slug ?? ''),
                        'product_name' => (string) ($product->name ?? ''),
                        'price' => round((float) $product->price, 2),
                        'currency' => 'HNL',
                        'published' => $published,
                        'embedding_model' => $model,
                        'embedding_hash' => $inputHash,
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->warn('Qdrant upsert skipped for product '.$product->id.': '.$exception->getMessage());

            return false;
        }
    }

    private function pruneStaleEmbeddings(
        QdrantClient $qdrantClient,
        ?string $tenantFilter,
        int $sinceHours,
        string $model,
    ): int {
        $embeddingsTable = 'directory_product_embeddings';
        $productsTable = (new Product)->getTable();
        $tenantsTable = (new Tenant)->getTable();
        $hasProductVisibility = Schema::hasColumn($productsTable, 'is_visible');
        $hasProductDeletedAt = Schema::hasColumn($productsTable, 'deleted_at');
        $hasProductStockStatus = Schema::hasColumn($productsTable, 'stock_status');
        $safeTenantFilter = is_string($tenantFilter) && trim($tenantFilter) !== '' ? trim($tenantFilter) : null;

        $affectedTenantIds = collect();
        if ($safeTenantFilter !== null) {
            $affectedTenantIds->push($safeTenantFilter);
        } elseif ($sinceHours > 0) {
            $since = now()->subHours($sinceHours);
            $affectedTenantIds = $affectedTenantIds
                ->merge(
                    Tenant::query()
                        ->withoutGlobalScopes()
                        ->where('updated_at', '>=', $since)
                        ->pluck('id')
                )
                ->merge(
                    Product::query()
                        ->withoutGlobalScope(TenantScope::class)
                        ->where('updated_at', '>=', $since)
                        ->pluck('tenant_id')
                );
        }

        $affectedTenantIds = $affectedTenantIds
            ->map(static fn (mixed $id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values();

        $query = DB::table($embeddingsTable.' as d')
            ->leftJoin($productsTable.' as p', 'p.id', '=', 'd.product_id')
            ->leftJoin($tenantsTable.' as t', 't.id', '=', 'd.tenant_id')
            ->select('d.product_id')
            ->where('d.embedding_model', $model)
            ->where(function ($innerQuery) use ($hasProductVisibility, $hasProductDeletedAt, $hasProductStockStatus): void {
                $innerQuery->whereNull('p.id')
                    ->orWhereNull('t.id')
                    ->orWhere('t.status', '!=', 'approved')
                    ->orWhere('t.is_active', false)
                    ->orWhereNotIn('t.subscription_status', ['active', 'trial']);

                if ($hasProductDeletedAt) {
                    $innerQuery->orWhereNotNull('p.deleted_at');
                }

                if ($hasProductVisibility) {
                    $innerQuery->orWhere('p.is_visible', false);
                }

                if ($hasProductStockStatus) {
                    $innerQuery->orWhereNotIn('p.stock_status', self::PURCHASEABLE_STOCK_STATUSES);
                }
            });

        if ($affectedTenantIds->isNotEmpty()) {
            $query->whereIn('d.tenant_id', $affectedTenantIds->all());
        }

        $staleProductIds = $query->pluck('d.product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($staleProductIds === []) {
            return 0;
        }

        DB::table($embeddingsTable)
            ->where('embedding_model', $model)
            ->whereIn('product_id', $staleProductIds)
            ->delete();

        if ($qdrantClient->enabled()) {
            try {
                $qdrantClient->deletePointsByIds($staleProductIds);
            } catch (Throwable $exception) {
                $this->warn('Qdrant stale prune skipped: '.$exception->getMessage());
            }
        }

        return count($staleProductIds);
    }

    /**
     * @param  list<float>  $embedding
     */
    private function syncPgvectorColumnIfAvailable(string $embeddingId, array $embedding): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('directory_product_embeddings', 'embedding')) {
            return;
        }

        $vectorLiteral = '['.implode(',', array_map(static fn (float $value): string => sprintf('%.8F', $value), $embedding)).']';

        try {
            DB::table('directory_product_embeddings')
                ->where('id', $embeddingId)
                ->update([
                    'embedding' => DB::raw("CAST('{$vectorLiteral}' AS vector)"),
                ]);
        } catch (\Throwable) {
            // Keep JSON embedding as canonical fallback when pgvector cast fails.
        }
    }
}
