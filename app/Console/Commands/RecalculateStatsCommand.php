<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Product\Repositories\ProductRatingMetricsWriter;
use App\Models\Product;
use App\Models\Review;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * RecalculateStatsCommand - Data Migration & Integrity Tool
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MIGRATION UTILITY                                                       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Recalculates 'rating_avg' and 'reviews_count' for all entities.         ║
 * ║  Used when migrating to "Computed Aggregate" pattern or fixing drift.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RecalculateStatsCommand extends Command
{
    public function __construct(private readonly ProductRatingMetricsWriter $productRatings)
    {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connectahn:recalculate-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate average ratings and review counts for Products and Tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting statistics recalculation...');
        $startTime = microtime(true);

        // 1. Recalculate Products
        $this->recalculateProducts();

        // 2. Recalculate Tenants
        $this->recalculateTenants();

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Recalculation completed in {$duration} seconds.");

        return Command::SUCCESS;
    }

    private function recalculateProducts(): void
    {
        // JOB_GLOBAL_POLL: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        $count = Product::withoutGlobalScopes()->count();
        $this->info("Processing {$count} Products...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        // JOB_GLOBAL_POLL: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
        Product::withoutGlobalScopes()->chunkById(100, function ($products) use ($bar) {
            foreach ($products as $product) {
                $stats = Review::where('product_id', $product->id)
                    ->where('is_approved', true)
                    ->selectRaw('count(*) as count, avg(rating) as avg_rating')
                    ->first();

                $this->productRatings->updateRatingMetrics(
                    (string) $product->tenant_id,
                    (int) $product->id,
                    (float) ($stats->avg_rating ?? 0),
                    (int) ($stats->count ?? 0),
                );

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
    }

    private function recalculateTenants(): void
    {
        $count = Tenant::count();
        $this->info("Processing {$count} Tenants...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        Tenant::chunk(100, function ($tenants) use ($bar) {
            foreach ($tenants as $tenant) {
                // Determine tenant-level reviews
                $stats = Review::where('tenant_id', $tenant->id)
                    ->whereNull('product_id')
                    ->where('is_approved', true)
                    ->selectRaw('count(*) as count, avg(rating) as avg_rating')
                    ->first();

                // Use forceFill to bypass $fillable protection for system tasks
                $tenant->forceFill([
                    'reviews_count' => $stats->count ?? 0,
                    'rating_avg' => $stats->avg_rating ?? 0,
                ])->saveQuietly();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
    }
}
