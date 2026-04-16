<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StockStatus;
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

final class SyncDirectoryEmbeddingsCommand extends Command
{
    protected $signature = 'directory:sync-embeddings
        {--tenant= : Sync embeddings only for a specific tenant UUID}
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
        $limit = max(0, (int) $this->option('limit'));
        $chunk = max(10, min((int) $this->option('chunk'), 500));
        $force = (bool) $this->option('force');

        $query = Product::query()
            ->withoutGlobalScope(TenantScope::class)
            ->select(['id', 'tenant_id', 'name', 'slug', 'price', 'description', 'stock_status', 'menu_section_id'])
            ->with(['tenant:id,name,slug,city_id,category_id', 'tenant.city:id,name,slug', 'tenant.category:id,name,slug', 'menuSection:id,name'])
            ->whereIn('stock_status', self::PURCHASEABLE_STOCK_STATUSES)
            ->whereHas('tenant', static function ($tenantQuery): void {
                $tenantQuery
                    ->where('is_active', true)
                    ->where('subscription_status', 'active');
            })
            ->orderBy('id');

        if (is_string($tenantFilter) && $tenantFilter !== '') {
            $query->where('tenant_id', $tenantFilter);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $model = (string) config('directory.guide.embedding_model', 'text-embedding-3-small');
        $processed = 0;
        $updated = 0;
        $skipped = 0;

        $query->chunkById($chunk, function ($products) use ($model, $force, &$processed, &$updated, &$skipped): void {
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

                $updated++;
            }
        });

        $this->info(sprintf(
            'Directory embeddings sync completed. processed=%d updated=%d skipped=%d',
            $processed,
            $updated,
            $skipped
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
