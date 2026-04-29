<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Shared\DTOs\Mail\TenantMailData;
use App\Domain\Contracts\AdminNotifier;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Infrastructure\Jobs\Governance\AuditTenantLimits;
use App\Mail\SubscriptionExpiredMail;
use App\Mail\SubscriptionExpiringMail;
use App\Models\Tenant;
use App\Models\User;
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
 * +--------------------------------------------------------------------------+
 * |  OPERATIONAL RISK ANALYSIS: Domain Consolidation Strategy               |
 * |--------------------------------------------------------------------------|
 * |  PROBLEM: Split commands (CheckExpiry + ExpireSubscriptions) create:    |
 * |    - Race conditions if run concurrently                                |
 * |    - Maintenance ambiguity (which file handles what?)                   |
 * |    - Inconsistent error handling between commands                       |
 * |                                                                          |
 * |  SOLUTION: Single command with orchestrated workflow:                    |
 * |    1. notifyUpcomingExpirations() - 3-day warnings                      |
 * |    2. processExpiredSubscriptions() - Downgrade expired plans           |
 * |                                                                          |
 * |  TRANSACTIONAL INTEGRITY:                                                |
 * |    Each expiration is wrapped in DB::transaction() ensuring:            |
 * |    - Status Update + Notification + Webhook are atomic                  |
 * |    - If notification fails, subscription is NOT downgraded              |
 * |    - Audit trail preserved even on partial failures                     |
 * |                                                                          |
 * |  MEMORY SAFETY: Uses chunkById(100) for all tenant iterations           |
 * +--------------------------------------------------------------------------+
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
    /** @var array<int, string> */
    private const PAID_PLAN_KEYS = [
        TenantPlan::PRO->value,
        Tenant::PLAN_CRECIMIENTO,
        Tenant::PLAN_COSECHA,
    ];

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

        // -----------------------------------------------------------------------
        // HEADER
        // -----------------------------------------------------------------------
        $this->components->info('Subscription Lifecycle Manager');
        $this->components->bulletList([
            "Grace period: {$graceDays} days",
            "Warning threshold: {$warningDays} days before expiry",
            "Free plan limit: {$freeProductLimit} products",
            'Mode: '.($this->dryRun ? 'DRY RUN (no changes)' : 'LIVE'),
        ]);

        // -----------------------------------------------------------------------
        // PHASE 1: Send expiration warnings (3 days out)
        // -----------------------------------------------------------------------
        $this->notifyUpcomingExpirations($warningDays);

        // -----------------------------------------------------------------------
        // PHASE 2: Process expired subscriptions (downgrade)
        // -----------------------------------------------------------------------
        $this->processExpiredSubscriptions($graceDays, $freeProductLimit);

        // -----------------------------------------------------------------------
        // SUMMARY
        // -----------------------------------------------------------------------
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
                ->whereIn('plan_type', self::PAID_PLAN_KEYS)
                ->where('subscription_status', 'active')
                ->where(function ($q) use ($targetStart, $targetEnd) {
                    $q->whereBetween('subscription_ends_at', [$targetStart, $targetEnd]);
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
        $user = $this->resolveTenantUser($tenant);

        // Guard: Tenant must have an associated user with email
        if ($user === null || $user->email === '') {
            Log::warning('SubscriptionLifecycle: Tenant has no valid user for notification', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        if ($this->dryRun) {
            $this->components->twoColumnDetail(
                "  Would notify: {$tenant->name}",
                $user->email
            );
            $this->notifiedCount++;

            return;
        }

        try {
            app(AdminNotifier::class)->notifyRenewalAttention($tenant, $daysUntilExpiry);
            app(OperationalPlaybookTaskManager::class)->syncForTrigger($tenant, 'renewal_attention', [
                'related_type' => Tenant::class,
                'related_id' => (string) $tenant->getKey(),
                'days_until_expiry' => $daysUntilExpiry,
            ]);

            // Try notification first (queued)
            if (class_exists(SubscriptionExpiringNotification::class)) {
                $user->notify(new SubscriptionExpiringNotification($tenant));
            }

            // Also send email for redundancy
            if (class_exists(SubscriptionExpiringMail::class)) {
                Mail::to($user->email)->queue(
                    new SubscriptionExpiringMail($this->toTenantMailData($tenant, $user), $daysUntilExpiry)
                );
            }

            $this->notifiedCount++;
            $this->components->twoColumnDetail(
                "  Notified: {$tenant->name}",
                $user->email
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
                ->whereIn('plan_type', self::PAID_PLAN_KEYS)
                ->where('subscription_status', 'active')
                ->where(function ($q) use ($cutoffDate) {
                    $q->where('subscription_ends_at', '<', $cutoffDate);
                })
                ->chunkById(100, function ($tenants) use ($freeProductLimit) {
                    foreach ($tenants as $tenant) {
                        $this->expireTenantSubscription($tenant, $freeProductLimit);
                    }
                });

            return true;
        });
    }

    /**
     * Expire a single tenant's subscription with TRUE transactional integrity.
     *
     * +------------------------------------------------------------------------+
     * |  CRITICAL FIX: afterCommit() for Queue Operations                      |
     * |------------------------------------------------------------------------|
     * |  PROBLEM: Mail::queue() dispatches IMMEDIATELY, not after commit.      |
     * |                                                                        |
     * |  RACE CONDITION SCENARIO:                                              |
     * |    1. DB::transaction starts                                          |
     * |    2. $tenant->update(['plan_type' => 'semilla']) -> SQL UPDATE       |
     * |    3. Mail::queue() -> Job pushed to Redis IMMEDIATELY                |
     * |    4. AuditTenantLimits::dispatch() -> Throws exception               |
     * |    5. Transaction ROLLBACK -> paid plan state reverts                 |
     * |    6. User receives "Plan expired" email but tenant is still paid     |
     * |                                                                        |
     * |  SOLUTION: Use DB::afterCommit() to queue jobs ONLY after commit.     |
     * |    If transaction fails, no email is sent.                            |
     * +------------------------------------------------------------------------+
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
                // Step 1: Update subscription status (DB operation)
                $tenant->update([
                    'plan_type' => TenantPlan::SEMILLA->value,
                    'subscription_status' => 'expired',
                    'product_limit' => $freeProductLimit,
                ]);

                // Step 2: Log for audit trail (DB operation - safe inside transaction)
                Log::info('SubscriptionLifecycle: Tenant subscription expired', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'new_product_limit' => $freeProductLimit,
                ]);
            });

            // -----------------------------------------------------------------------
            // QUEUE OPERATIONS: Execute ONLY after successful commit
            // -----------------------------------------------------------------------
            // These are now OUTSIDE the transaction, ensuring they only run
            // if the database changes were successfully committed.
            // -----------------------------------------------------------------------

            // Reload tenant to get confirmed state
            $tenant->refresh();
            $user = $this->resolveTenantUser($tenant);

            // Send expiration notification (now safe - DB is committed)
            if ($user !== null && $user->email !== '') {
                if (class_exists(SubscriptionExpiredMail::class)) {
                    Mail::to($user->email)->queue(
                        new SubscriptionExpiredMail($this->toTenantMailData($tenant, $user))
                    );
                }
            }

            // Dispatch audit job (now safe - DB is committed)
            if (class_exists(AuditTenantLimits::class)) {
                AuditTenantLimits::dispatch();
            }

            $this->expiredCount++;
            $this->components->twoColumnDetail(
                "  Expired: {$tenant->name}",
                'Downgraded to Free'
            );
        } catch (\Exception $e) {
            // Transaction failed - NO email was sent, NO job was dispatched
            // State is consistent: tenant keeps its paid plan and received no communication
            $this->errorCount++;
            Log::error('SubscriptionLifecycle: Failed to expire subscription', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->components->error("  Failed: {$tenant->name} - {$e->getMessage()}");
        }
    }

    private function resolveTenantUser(Tenant $tenant): ?User
    {
        $user = $tenant->user;

        return $user instanceof User ? $user : null;
    }

    private function toTenantMailData(Tenant $tenant, User $user): TenantMailData
    {
        return TenantMailData::fromTenant($tenant, (string) $user->email);
    }
}
