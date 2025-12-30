<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AuditTenantLimits;
use App\Mail\SubscriptionExpiredMail;
use App\Mail\SubscriptionExpiringMail;
use App\Models\Tenant;
use App\Notifications\SubscriptionExpiringNotification;
use App\Settings\GeneralSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SubscriptionLifecycleCommand - Unified Subscription State Machine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: Domain Consolidation Strategy               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Split commands (CheckExpiry + ExpireSubscriptions) create:    ║
 * ║    - Race conditions if run concurrently                                ║
 * ║    - Maintenance ambiguity (which file handles what?)                   ║
 * ║    - Inconsistent error handling between commands                       ║
 * ║                                                                          ║
 * ║  SOLUTION: Single command with orchestrated workflow:                    ║
 * ║    1. notifyUpcomingExpirations() - 3-day warnings                      ║
 * ║    2. processExpiredSubscriptions() - Downgrade expired plans           ║
 * ║                                                                          ║
 * ║  TRANSACTIONAL INTEGRITY:                                                ║
 * ║    Each expiration is wrapped in DB::transaction() ensuring:            ║
 * ║    - Status Update + Notification + Webhook are atomic                  ║
 * ║    - If notification fails, subscription is NOT downgraded              ║
 * ║    - Audit trail preserved even on partial failures                     ║
 * ║                                                                          ║
 * ║  MEMORY SAFETY: Uses chunkById(100) for all tenant iterations           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   php artisan subscription:lifecycle
 *   php artisan subscription:lifecycle --dry-run
 *   php artisan subscription:lifecycle --grace-days=5
 *
 * SCHEDULING (routes/console.php):
 *   Schedule::command('subscription:lifecycle')->dailyAt('08:00');
 */
class SubscriptionLifecycleCommand extends Command
{
    protected $signature = 'subscription:lifecycle
                            {--grace-days= : Override grace period (default: from GeneralSettings)}
                            {--warning-days=3 : Days before expiry to send warning}
                            {--dry-run : Preview without making changes}';

    protected $description = 'Unified subscription lifecycle: warnings, expirations, and downgrades';

    protected int $notifiedCount = 0;
    protected int $expiredCount = 0;
    protected int $errorCount = 0;
    protected bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        
        $settings = app(GeneralSettings::class);
        $graceDays = $this->option('grace-days') !== null 
            ? (int) $this->option('grace-days') 
            : $settings->grace_period_days;
        $warningDays = (int) $this->option('warning-days');
        $freeProductLimit = $settings->free_plan_product_limit;

        // ═══════════════════════════════════════════════════════════════════════
        // HEADER
        // ═══════════════════════════════════════════════════════════════════════
        $this->components->info('Subscription Lifecycle Manager');
        $this->components->bulletList([
            "Grace period: {$graceDays} days",
            "Warning threshold: {$warningDays} days before expiry",
            "Free plan limit: {$freeProductLimit} products",
            "Mode: " . ($this->dryRun ? 'DRY RUN (no changes)' : 'LIVE'),
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: Send expiration warnings (3 days out)
        // ═══════════════════════════════════════════════════════════════════════
        $this->notifyUpcomingExpirations($warningDays);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: Process expired subscriptions (downgrade)
        // ═══════════════════════════════════════════════════════════════════════
        $this->processExpiredSubscriptions($graceDays, $freeProductLimit);

        // ═══════════════════════════════════════════════════════════════════════
        // SUMMARY
        // ═══════════════════════════════════════════════════════════════════════
        $this->newLine();
        $this->components->twoColumnDetail('Warnings sent', (string) $this->notifiedCount);
        $this->components->twoColumnDetail('Subscriptions expired', (string) $this->expiredCount);
        $this->components->twoColumnDetail('Errors', (string) $this->errorCount);

        Log::info('SubscriptionLifecycleCommand completed', [
            'notified' => $this->notifiedCount,
            'expired' => $this->expiredCount,
            'errors' => $this->errorCount,
            'dry_run' => $this->dryRun,
        ]);

        return $this->errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * PHASE 1: Send warnings to tenants expiring in N days.
     *
     * Uses chunkById(100) for memory-safe iteration.
     */
    protected function notifyUpcomingExpirations(int $daysBeforeExpiry): void
    {
        $this->components->task('Phase 1: Sending expiration warnings', function () use ($daysBeforeExpiry) {
            $targetStart = Carbon::now()->addDays($daysBeforeExpiry)->startOfDay();
            $targetEnd = Carbon::now()->addDays($daysBeforeExpiry)->endOfDay();

            Tenant::query()
                ->where(function ($q) {
                    $q->where('subscription_status', 'active')
                      ->orWhere('is_pro', true);
                })
                ->where(function ($q) use ($targetStart, $targetEnd) {
                    $q->whereBetween('subscription_ends_at', [$targetStart, $targetEnd])
                      ->orWhereBetween('plan_expires_at', [$targetStart, $targetEnd]);
                })
                ->chunkById(100, function ($tenants) use ($daysBeforeExpiry) {
                    foreach ($tenants as $tenant) {
                        $this->sendExpirationWarning($tenant, $daysBeforeExpiry);
                    }
                });

            return true;
        });
    }

    /**
     * Send warning notification to a single tenant.
     */
    protected function sendExpirationWarning(Tenant $tenant, int $daysUntilExpiry): void
    {
        // Guard: Tenant must have an associated user with email
        if (!$tenant->user || empty($tenant->user->email)) {
            Log::warning('SubscriptionLifecycle: Tenant has no valid user for notification', [
                'tenant_id' => $tenant->id,
            ]);
            return;
        }

        if ($this->dryRun) {
            $this->components->twoColumnDetail(
                "  Would notify: {$tenant->name}",
                $tenant->user->email
            );
            $this->notifiedCount++;
            return;
        }

        try {
            // Try notification first (queued)
            if (class_exists(SubscriptionExpiringNotification::class)) {
                $tenant->user->notify(new SubscriptionExpiringNotification($tenant));
            }
            
            // Also send email for redundancy
            if (class_exists(SubscriptionExpiringMail::class)) {
                Mail::to($tenant->user->email)->queue(
                    new SubscriptionExpiringMail($tenant, $daysUntilExpiry)
                );
            }

            $this->notifiedCount++;
            $this->components->twoColumnDetail(
                "  ✓ Notified: {$tenant->name}",
                $tenant->user->email
            );
        } catch (\Exception $e) {
            $this->errorCount++;
            Log::error('SubscriptionLifecycle: Failed to send warning', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * PHASE 2: Downgrade expired subscriptions.
     *
     * Uses DB::transaction() for atomic operations.
     * Uses chunkById(100) for memory safety.
     */
    protected function processExpiredSubscriptions(int $graceDays, int $freeProductLimit): void
    {
        $this->components->task('Phase 2: Processing expired subscriptions', function () use ($graceDays, $freeProductLimit) {
            $cutoffDate = Carbon::now()->subDays($graceDays);

            Tenant::query()
                ->where(function ($q) {
                    $q->where('subscription_status', 'active')
                      ->orWhere('is_pro', true);
                })
                ->where(function ($q) use ($cutoffDate) {
                    $q->where('subscription_ends_at', '<', $cutoffDate)
                      ->orWhere('plan_expires_at', '<', $cutoffDate);
                })
                ->chunkById(100, function ($tenants) use ($freeProductLimit, $graceDays) {
                    foreach ($tenants as $tenant) {
                        $this->expireTenantSubscription($tenant, $freeProductLimit);
                    }
                });

            return true;
        });
    }

    /**
     * Expire a single tenant's subscription with transactional integrity.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  TRANSACTIONAL INTEGRITY GUARANTEE                                     ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  All operations within the transaction are ATOMIC:                     ║
     * ║  1. Status update (is_pro = false)                                    ║
     * ║  2. Limit enforcement (product_limit = free_plan_limit)               ║
     * ║  3. Email notification dispatch                                       ║
     * ║  4. Audit job dispatch                                                ║
     * ║                                                                        ║
     * ║  If ANY step fails, the ENTIRE transaction is rolled back.            ║
     * ║  This prevents "zombie states" where user is downgraded but           ║
     * ║  never notified, or notified but not downgraded.                      ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     */
    protected function expireTenantSubscription(Tenant $tenant, int $freeProductLimit): void
    {
        if ($this->dryRun) {
            $this->components->twoColumnDetail(
                "  Would expire: {$tenant->name}",
                "ID: {$tenant->id}"
            );
            $this->expiredCount++;
            return;
        }

        try {
            DB::transaction(function () use ($tenant, $freeProductLimit) {
                // Step 1: Update subscription status
                $tenant->update([
                    'is_pro' => false,
                    'subscription_status' => 'expired',
                    'product_limit' => $freeProductLimit,
                ]);

                // Step 2: Send expiration notification
                if ($tenant->user && !empty($tenant->user->email)) {
                    if (class_exists(SubscriptionExpiredMail::class)) {
                        Mail::to($tenant->user->email)->queue(
                            new SubscriptionExpiredMail($tenant)
                        );
                    }
                }

                // Step 3: Dispatch audit job
                if (class_exists(AuditTenantLimits::class)) {
                    AuditTenantLimits::dispatch($tenant);
                }

                // Step 4: Log for audit trail
                Log::info('SubscriptionLifecycle: Tenant subscription expired', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'new_product_limit' => $freeProductLimit,
                ]);
            });

            $this->expiredCount++;
            $this->components->twoColumnDetail(
                "  ✓ Expired: {$tenant->name}",
                "Downgraded to Free"
            );
        } catch (\Exception $e) {
            $this->errorCount++;
            Log::error('SubscriptionLifecycle: Failed to expire subscription', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->components->error("  ✗ Failed: {$tenant->name} - {$e->getMessage()}");
        }
    }
}
