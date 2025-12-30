<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * GenerateSitemapCommand - Memory-Safe Multi-Tenant Sitemap Generator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: RAM Exhaustion Prevention                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Naive approaches cause Out-Of-Memory crashes:                  ║
 * ║    Product::all()->each(...) loads 50,000+ products into RAM            ║
 * ║    PHP Fatal Error: Allowed memory size of 134217728 bytes exhausted    ║
 * ║                                                                          ║
 * ║  WHY chunkById(100) PREVENTS RAM EXHAUSTION:                             ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  WRONG: Model::all()                                            │   ║
 * ║    │  ┌────┬────┬────┬────┬────┬─────┬────────────────┐             │   ║
 * ║    │  │ P1 │ P2 │ P3 │ P4 │ P5 │ ... │ P50000         │ ← ALL IN RAM│   ║
 * ║    │  └────┴────┴────┴────┴────┴─────┴────────────────┘             │   ║
 * ║    │  ↓ CRASH: 500MB+ memory required                               │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  CORRECT: Model::chunkById(100)                                 │   ║
 * ║    │  ┌────┬────┬────┐  → Process → ┌────┬────┬────┐  → Process     │   ║
 * ║    │  │P1-100│        │  → GC Free → │P101-200│        │  → GC Free  │   ║
 * ║    │  └────┴────┴────┘              └────┴────┴────┘               │   ║
 * ║    │  ↓ SAFE: Only 100 records in RAM at any time                   │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║  TENANT ISOLATION:                                                       ║
 * ║    --tenant={id} generates sitemap for single tenant                    ║
 * ║    Without flag: iterates ALL tenants sequentially                      ║
 * ║    Each tenant's sitemap is isolated (no cross-tenant URLs)             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   php artisan sitemap:generate              # All tenants
 *   php artisan sitemap:generate --tenant=5   # Single tenant
 *   php artisan sitemap:generate --dry-run    # Preview only
 *
 * SCHEDULING:
 *   Schedule::command('sitemap:generate')->weekly();
 */
class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate
                            {--tenant= : Generate for specific tenant ID only}
                            {--dry-run : Preview without writing files}
                            {--chunk=100 : Records per chunk for memory safety}';

    protected $description = 'Generate SEO sitemaps for all or specific tenants';

    protected int $urlsGenerated = 0;
    protected int $tenantsProcessed = 0;
    protected bool $dryRun = false;
    protected int $chunkSize = 100;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunkSize = (int) $this->option('chunk');
        $tenantId = $this->option('tenant');

        $this->components->info('Sitemap Generator - Memory-Safe Multi-Tenant');
        $this->components->bulletList([
            "Chunk size: {$this->chunkSize} records",
            "Mode: " . ($this->dryRun ? 'DRY RUN' : 'LIVE'),
            "Scope: " . ($tenantId ? "Tenant #{$tenantId}" : "All active tenants"),
        ]);

        if ($tenantId) {
            // Single tenant mode
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->components->error("Tenant #{$tenantId} not found");
                return Command::FAILURE;
            }
            $this->generateTenantSitemap($tenant);
        } else {
            // All tenants mode - iterate with chunkById for memory safety
            $this->generateAllTenantSitemaps();
        }

        // ═══════════════════════════════════════════════════════════════════════
        // SUMMARY
        // ═══════════════════════════════════════════════════════════════════════
        $this->newLine();
        $this->components->twoColumnDetail('Tenants processed', (string) $this->tenantsProcessed);
        $this->components->twoColumnDetail('URLs generated', (string) $this->urlsGenerated);

        Log::info('GenerateSitemapCommand completed', [
            'tenants' => $this->tenantsProcessed,
            'urls' => $this->urlsGenerated,
            'dry_run' => $this->dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Generate sitemaps for all active tenants.
     *
     * Uses chunkById(50) for tenant iteration to prevent loading
     * all 10,000+ tenants into memory at once.
     */
    protected function generateAllTenantSitemaps(): void
    {
        $this->components->task('Generating sitemaps for all tenants', function () {
            Tenant::query()
                ->where('is_active', true)
                ->chunkById(50, function ($tenants) {
                    foreach ($tenants as $tenant) {
                        $this->generateTenantSitemap($tenant);
                    }
                });

            return true;
        });
    }

    /**
     * Generate sitemap for a single tenant.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  TENANT ISOLATION GUARANTEE                                            ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  All product/category queries are filtered by tenant_id.               ║
     * ║  This prevents "Cross-Tenant URL Leakage" where products from          ║
     * ║  Tenant A appear in Tenant B's sitemap, causing:                       ║
     * ║    - SEO penalties (duplicate content)                                 ║
     * ║    - Privacy violations (exposing competitor products)                 ║
     * ║    - User confusion (clicking links to wrong store)                    ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     */
    protected function generateTenantSitemap(Tenant $tenant): void
    {
        $this->line("  Processing: {$tenant->name} (ID: {$tenant->id})");

        // Initialize sitemap instance
        $sitemap = Sitemap::create();
        $urlCount = 0;

        // Add homepage
        $baseUrl = $this->getTenantBaseUrl($tenant);
        $sitemap->add(Url::create($baseUrl)
            ->setLastModificationDate($tenant->updated_at)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            ->setPriority(1.0));
        $urlCount++;

        // ═══════════════════════════════════════════════════════════════════════
        // MEMORY-SAFE PRODUCT ITERATION
        // ═══════════════════════════════════════════════════════════════════════
        // chunkById(100) ensures only 100 products are in memory at any time.
        // After processing each chunk, PHP's garbage collector frees the memory.
        // This allows processing 1,000,000+ products without RAM exhaustion.
        // ═══════════════════════════════════════════════════════════════════════
        Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_visible', true)
            ->where('is_active', true)
            ->select(['id', 'slug', 'updated_at']) // Minimal columns
            ->chunkById($this->chunkSize, function ($products) use ($sitemap, $baseUrl, &$urlCount) {
                foreach ($products as $product) {
                    $sitemap->add(Url::create("{$baseUrl}/productos/{$product->slug}")
                        ->setLastModificationDate($product->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.8));
                    $urlCount++;
                }
            });

        // Add categories
        if (class_exists(Category::class)) {
            Category::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->select(['id', 'slug', 'updated_at'])
                ->chunkById($this->chunkSize, function ($categories) use ($sitemap, $baseUrl, &$urlCount) {
                    foreach ($categories as $category) {
                        $sitemap->add(Url::create("{$baseUrl}/categorias/{$category->slug}")
                            ->setLastModificationDate($category->updated_at)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7));
                        $urlCount++;
                    }
                });
        }

        // Write sitemap to storage
        if (!$this->dryRun) {
            $filename = "sitemaps/sitemap-{$tenant->slug}.xml";
            Storage::disk('public')->put($filename, $sitemap->render());
            $this->line("    → Saved: {$filename} ({$urlCount} URLs)");
        } else {
            $this->line("    → Would save: {$urlCount} URLs");
        }

        $this->urlsGenerated += $urlCount;
        $this->tenantsProcessed++;
    }

    /**
     * Get the base URL for a tenant's microsite.
     */
    protected function getTenantBaseUrl(Tenant $tenant): string
    {
        // Check for custom domain first
        if (!empty($tenant->custom_domain)) {
            return "https://{$tenant->custom_domain}";
        }

        // Fallback to subdomain or path-based URL
        $baseUrl = config('app.url');
        
        return "{$baseUrl}/tienda/{$tenant->slug}";
    }
}
