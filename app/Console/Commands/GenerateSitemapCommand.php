<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * GenerateSitemapCommand - Memory-Safe Streaming Sitemap Generator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: Streaming XML to Prevent RAM Exhaustion     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Spatie Sitemap accumulates ALL URLs in memory before render.  ║
 * ║    50,000 products → 50,000 Url objects → 50MB+ RAM → CRASH             ║
 * ║                                                                          ║
 * ║  WHY STREAMING XML SOLVES THIS:                                          ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  WRONG: $sitemap->add() in loop                                 │   ║
 * ║    │  ┌────┬────┬────┬────┬────┬─────┬────────────────┐             │   ║
 * ║    │  │URL1│URL2│URL3│URL4│URL5│ ... │ URL50000       │ ← ALL IN RAM│   ║
 * ║    │  └────┴────┴────┴────┴────┴─────┴────────────────┘             │   ║
 * ║    │  → $sitemap->render() → Build 50MB XML string → CRASH          │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  CORRECT: Stream write to file                                  │   ║
 * ║    │  Process 100 → Write to disk → GC → Process 100 → Write...     │   ║
 * ║    │  ↓ RAM: Only 100 products + 1 XML tag at any time              │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║  SITEMAP INDEX: For >50,000 URLs, generate multiple files + index.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   php artisan sitemap:generate              # All tenants
 *   php artisan sitemap:generate --tenant=5   # Single tenant
 *
 * SCHEDULING:
 *   Schedule::command('sitemap:generate')->weekly();
 */
class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate
                            {--tenant= : Generate for specific tenant ID only}
                            {--dry-run : Preview without writing files}
                            {--max-urls=50000 : Maximum URLs per sitemap file}';

    protected $description = 'Generate SEO sitemaps using memory-safe streaming';

    protected int $urlsGenerated = 0;
    protected int $tenantsProcessed = 0;
    protected int $sitemapsCreated = 0;
    protected bool $dryRun = false;
    protected int $maxUrlsPerSitemap = 50000;

    /** @var resource|null File handle for streaming */
    protected $fileHandle = null;
    protected int $currentUrlCount = 0;
    protected int $currentSitemapIndex = 1;
    protected string $currentTenantSlug = '';

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->maxUrlsPerSitemap = (int) $this->option('max-urls');
        $tenantId = $this->option('tenant');

        $this->components->info('Sitemap Generator - Memory-Safe Streaming');
        $this->components->bulletList([
            "Max URLs per file: {$this->maxUrlsPerSitemap}",
            "Mode: " . ($this->dryRun ? 'DRY RUN' : 'LIVE'),
            "Scope: " . ($tenantId ? "Tenant #{$tenantId}" : "All active tenants"),
        ]);

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->components->error("Tenant #{$tenantId} not found");
                return Command::FAILURE;
            }
            $this->generateTenantSitemap($tenant);
        } else {
            $this->generateAllTenantSitemaps();
        }

        $this->newLine();
        $this->components->twoColumnDetail('Tenants processed', (string) $this->tenantsProcessed);
        $this->components->twoColumnDetail('URLs generated', (string) $this->urlsGenerated);
        $this->components->twoColumnDetail('Sitemap files created', (string) $this->sitemapsCreated);

        Log::info('GenerateSitemapCommand completed', [
            'tenants' => $this->tenantsProcessed,
            'urls' => $this->urlsGenerated,
            'files' => $this->sitemapsCreated,
            'dry_run' => $this->dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Generate sitemaps for all active tenants.
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
     * Generate sitemap for a single tenant using STREAMING XML.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  STREAMING IMPLEMENTATION                                              ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  1. Open file handle with fopen()                                     ║
     * ║  2. Write XML header                                                  ║
     * ║  3. For each chunk of 100 products:                                   ║
     * ║     - fwrite() each <url> element directly to disk                   ║
     * ║     - PHP garbage collects the 100 Eloquent models                   ║
     * ║  4. Write XML footer and fclose()                                    ║
     * ║                                                                        ║
     * ║  Result: Constant ~10MB RAM regardless of dataset size               ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     */
    protected function generateTenantSitemap(Tenant $tenant): void
    {
        $this->line("  Processing: {$tenant->name} (ID: {$tenant->id})");
        
        $this->currentTenantSlug = $tenant->slug;
        $this->currentSitemapIndex = 1;
        $this->currentUrlCount = 0;

        $baseUrl = $this->getTenantBaseUrl($tenant);

        try {
            if (!$this->dryRun) {
                $this->startNewSitemapFile($tenant->slug);
            }

            // Add homepage
            $this->writeUrlToSitemap($baseUrl, $tenant->updated_at, 'daily', 1.0);

            // ═══════════════════════════════════════════════════════════════════════
            // STREAM PRODUCTS - Memory safe with chunkById + direct file write
            // ═══════════════════════════════════════════════════════════════════════
            Product::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_visible', true)
                ->where('is_active', true)
                ->select(['id', 'slug', 'updated_at'])
                ->chunkById(100, function ($products) use ($baseUrl) {
                    foreach ($products as $product) {
                        $this->writeUrlToSitemap(
                            "{$baseUrl}/productos/{$product->slug}",
                            $product->updated_at,
                            'weekly',
                            0.8
                        );
                    }
                });

            // Stream categories
            if (class_exists(Category::class)) {
                Category::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->select(['id', 'slug', 'updated_at'])
                    ->chunkById(100, function ($categories) use ($baseUrl) {
                        foreach ($categories as $category) {
                            $this->writeUrlToSitemap(
                                "{$baseUrl}/categorias/{$category->slug}",
                                $category->updated_at,
                                'weekly',
                                0.7
                            );
                        }
                    });
            }

        } finally {
            // ═══════════════════════════════════════════════════════════════════════
            // CRITICAL: Always close file handle, even on exception
            // ═══════════════════════════════════════════════════════════════════════
            // Without this, file handles leak on exception, causing:
            // - "Too many open files" errors after many tenants
            // - Corrupted/truncated XML files
            // - Resource exhaustion
            // ═══════════════════════════════════════════════════════════════════════
            if (!$this->dryRun) {
                $this->closeSitemapFile();
            }
        }

        // Generate sitemap index if multiple files
        if ($this->currentSitemapIndex > 1) {
            $this->generateSitemapIndex($tenant->slug, $this->currentSitemapIndex);
        }

        $this->tenantsProcessed++;
        $this->line("    → Created {$this->currentSitemapIndex} sitemap file(s)");
    }

    /**
     * Write a single URL to the current sitemap file.
     * Handles automatic file rotation when max URLs reached.
     */
    protected function writeUrlToSitemap(string $url, $lastmod, string $changefreq, float $priority): void
    {
        $this->urlsGenerated++;
        $this->currentUrlCount++;

        if ($this->dryRun) {
            return;
        }

        // Rotate file if we've hit the limit
        if ($this->currentUrlCount > $this->maxUrlsPerSitemap) {
            $this->closeSitemapFile();
            $this->currentSitemapIndex++;
            $this->startNewSitemapFile($this->currentTenantSlug);
            $this->currentUrlCount = 1;
        }

        // Format the date
        $date = $lastmod instanceof \DateTime 
            ? $lastmod->format('Y-m-d') 
            : (is_string($lastmod) ? $lastmod : now()->format('Y-m-d'));

        // Write URL entry directly to file (no memory accumulation)
        $urlXml = <<<XML
    <url>
        <loc>{$this->escapeXml($url)}</loc>
        <lastmod>{$date}</lastmod>
        <changefreq>{$changefreq}</changefreq>
        <priority>{$priority}</priority>
    </url>

XML;

        fwrite($this->fileHandle, $urlXml);
    }

    /**
     * Start a new sitemap file with XML header.
     */
    protected function startNewSitemapFile(string $tenantSlug): void
    {
        $filename = $this->getSitemapFilename($tenantSlug, $this->currentSitemapIndex);
        $path = Storage::disk('public')->path($filename);
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->fileHandle = fopen($path, 'w');
        $this->sitemapsCreated++;

        // Write XML header
        $header = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

XML;

        fwrite($this->fileHandle, $header);
    }

    /**
     * Close current sitemap file with XML footer.
     */
    protected function closeSitemapFile(): void
    {
        if ($this->fileHandle) {
            fwrite($this->fileHandle, "</urlset>\n");
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    /**
     * Generate sitemap index file for multiple sitemaps.
     */
    protected function generateSitemapIndex(string $tenantSlug, int $totalFiles): void
    {
        $baseUrl = config('app.url');
        $indexPath = Storage::disk('public')->path("sitemaps/sitemap-{$tenantSlug}-index.xml");

        $handle = fopen($indexPath, 'w');
        
        $header = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

XML;
        fwrite($handle, $header);

        for ($i = 1; $i <= $totalFiles; $i++) {
            $sitemapUrl = "{$baseUrl}/storage/sitemaps/sitemap-{$tenantSlug}-{$i}.xml";
            $entry = <<<XML
    <sitemap>
        <loc>{$sitemapUrl}</loc>
        <lastmod>{$this->escapeXml(now()->format('Y-m-d'))}</lastmod>
    </sitemap>

XML;
            fwrite($handle, $entry);
        }

        fwrite($handle, "</sitemapindex>\n");
        fclose($handle);

        $this->line("    → Generated sitemap index with {$totalFiles} files");
    }

    /**
     * Get sitemap filename for a tenant.
     */
    protected function getSitemapFilename(string $tenantSlug, int $index): string
    {
        if ($index === 1) {
            return "sitemaps/sitemap-{$tenantSlug}.xml";
        }
        return "sitemaps/sitemap-{$tenantSlug}-{$index}.xml";
    }

    /**
     * Escape special XML characters.
     */
    protected function escapeXml(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the base URL for a tenant's microsite.
     */
    protected function getTenantBaseUrl(Tenant $tenant): string
    {
        if (!empty($tenant->custom_domain)) {
            return "https://{$tenant->custom_domain}";
        }

        return config('app.url') . "/tienda/{$tenant->slug}";
    }
}
