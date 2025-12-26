<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\SubscriptionExpiringNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CheckSubscriptionExpiry - Watchdog Command for Proactive Retention
 *
 * MISSION:
 * Daily automated scan for subscriptions expiring in 3 days.
 * Dispatches retention notifications to prevent involuntary churn.
 *
 * MEMORY SAFETY:
 * Uses chunkById(100) pattern instead of get()/all() to ensure
 * scalability to 100,000+ tenants without RAM exhaustion.
 *
 * ARCHITECTURE:
 * - Runs daily at 08:00 via Laravel Scheduler
 * - Query: subscription_status = 'active' AND subscription_ends_at = 3 days from now
 * - Action: Dispatch SubscriptionExpiringNotification (async, queued)
 *
 * USAGE:
 * php artisan subscriptions:check-expiry
 *
 * SCHEDULING (routes/console.php):
 * Schedule::command('subscriptions:check-expiry')->dailyAt('08:00');
 */
class CheckSubscriptionExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expiry
                            {--days=3 : Days before expiry to trigger notification}
                            {--dry-run : Preview without sending notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring subscriptions and notify tenants (retention engine)';

    /**
     * Execute the console command.
     *
     * MEMORY SAFETY IMPLEMENTATION:
     * Uses chunkById(100) to process tenants in batches.
     * Each chunk is garbage collected before loading the next.
     * This ensures constant memory usage regardless of dataset size.
     */
    public function handle(): int
    {
        $daysUntilExpiry = (int) $this->option('days');
        $isDryRun = $this->option('dry-run');

        // Calculate the target expiry date (exactly N days from now)
        $targetDate = Carbon::now()->addDays($daysUntilExpiry)->startOfDay();
        $targetDateEnd = Carbon::now()->addDays($daysUntilExpiry)->endOfDay();

        $this->info("🔍 Scanning for subscriptions expiring in {$daysUntilExpiry} days...");
        $this->info("📅 Target date range: {$targetDate->toDateString()} to {$targetDateEnd->toDateString()}");

        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE - No notifications will be sent');
        }

        $processedCount = 0;
        $errorCount = 0;

        // ═══════════════════════════════════════════════════════════════════════
        // MEMORY-SAFE CHUNKED PROCESSING
        // ═══════════════════════════════════════════════════════════════════════
        // chunkById(100) loads only 100 records at a time into memory.
        // After processing each chunk, PHP garbage collector frees the memory.
        // This pattern scales to millions of records safely.
        // ═══════════════════════════════════════════════════════════════════════

        Tenant::query()
            ->where('subscription_status', 'active')
            ->whereBetween('subscription_ends_at', [$targetDate, $targetDateEnd])
            ->chunkById(100, function ($tenants) use (&$processedCount, &$errorCount, $isDryRun) {
                foreach ($tenants as $tenant) {
                    try {
                        // Defensive: Ensure tenant has a valid user to notify
                        if (!$tenant->user) {
                            $this->warn("⚠️  Tenant #{$tenant->id} ({$tenant->name}) has no associated user - skipping");
                            Log::warning('CheckSubscriptionExpiry: Tenant has no user', [
                                'tenant_id' => $tenant->id,
                                'tenant_name' => $tenant->name,
                            ]);
                            continue;
                        }

                        // Defensive: Ensure user has email
                        if (empty($tenant->user->email)) {
                            $this->warn("⚠️  Tenant #{$tenant->id} user has no email - skipping");
                            continue;
                        }

                        if ($isDryRun) {
                            $this->line("  📧 Would notify: {$tenant->name} ({$tenant->user->email})");
                        } else {
                            // Dispatch async notification to queue
                            $tenant->user->notify(new SubscriptionExpiringNotification($tenant));
                            $this->line("  ✅ Notified: {$tenant->name} ({$tenant->user->email})");
                        }

                        $processedCount++;
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("  ❌ Error processing tenant #{$tenant->id}: {$e->getMessage()}");

                        Log::error('CheckSubscriptionExpiry: Failed to notify tenant', [
                            'tenant_id' => $tenant->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            });

        // ═══════════════════════════════════════════════════════════════════════
        // SUMMARY
        // ═══════════════════════════════════════════════════════════════════════

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info("✅ Processing complete");
        $this->info("   Tenants processed: {$processedCount}");

        if ($errorCount > 0) {
            $this->warn("   Errors: {$errorCount}");
        }

        if ($isDryRun) {
            $this->warn('   (Dry run - no notifications actually sent)');
        }

        $this->info('═══════════════════════════════════════════════════════════════');

        // Log execution for audit trail
        Log::info('CheckSubscriptionExpiry command completed', [
            'days_until_expiry' => $daysUntilExpiry,
            'tenants_processed' => $processedCount,
            'errors' => $errorCount,
            'dry_run' => $isDryRun,
        ]);

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
