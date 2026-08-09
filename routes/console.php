<?php

use App\Infrastructure\Jobs\Governance\DispatchStaleOrderCleanup;
use App\Jobs\DispatchAppointmentReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// PR5: Scheduled reports processor
Schedule::command('reports:send-scheduled')->hourly()->withoutOverlapping();

// Resume canonical workflow runs whose wait step has elapsed.
Schedule::command('workflows:resume-due')->everyMinute()->withoutOverlapping();

// Claim due marketing campaigns and enqueue tenant-scoped recipient deliveries.
Schedule::command('campaigns:dispatch-due')->everyMinute()->withoutOverlapping();

// Read-only internal/runtime policy audit. External providers remain explicitly blocked until live verification.
Schedule::command('dovra:operations-readiness --json')
    ->dailyAt('02:45')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/operations-readiness.log'));

Schedule::command('dovra:validate-workforce --dry-run --json')
    ->dailyAt('02:50')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/workforce-readiness.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ═══════════════════════════════════════════════════════════════════════════════
// AUTOMATED RETENTION ENGINE - Subscription Expiry Checker
// ═══════════════════════════════════════════════════════════════════════════════
// Runs daily at 08:00 AM to check for subscriptions expiring in 3 days.
// Dispatches SubscriptionExpiringNotification to tenant owners.
// Memory-safe: Uses chunkById(100) pattern to scale to 100k+ tenants.
// ═══════════════════════════════════════════════════════════════════════════════
Schedule::command('subscriptions:check-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/subscription-checker.log'));

// ═══════════════════════════════════════════════════════════════════════════════
// AUTOMATED INVENTORY RECOVERY - Stale Order Garbage Collector
// ═══════════════════════════════════════════════════════════════════════════════
// Runs every 15 minutes to release inventory locked by abandoned checkouts.
// Cancels PENDING orders older than 30 minutes and restores stock_quantity.
// Prevents "Inventory Hoarding" from zombie orders.
// ═══════════════════════════════════════════════════════════════════════════════
Schedule::job(new DispatchStaleOrderCleanup)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// ═══════════════════════════════════════════════════════════════════════════════
// STORAGE GARBAGE COLLECTOR - Orphaned Upload Cleanup
// ═══════════════════════════════════════════════════════════════════════════════
// Runs hourly to clean files uploaded but never linked to a database record.
// Handles edge case: PHP crash between file upload and DB insert.
// Grace period: 60 minutes (won't delete files still being processed).
// ═══════════════════════════════════════════════════════════════════════════════
Schedule::command('storage:cleanup-orphans --grace=60')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/orphan-cleanup.log'));

Schedule::command('storage:cleanup-product-staging --grace=60')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/product-staging-cleanup.log'));

Schedule::command('storage:process-product-media-cleanup --limit=100')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/product-media-cleanup.log'));

// ═══════════════════════════════════════════════════════════════════════════════
// TRANSACTIONAL OUTBOX WORKER - Domain Event Relay
// ═══════════════════════════════════════════════════════════════════════════════
// Runs every minute to dispatch pending domain events from the outbox table.
// Guarantees at-least-once delivery even if app crashes after DB commit.
// Manual run: php artisan outbox:process
// ═══════════════════════════════════════════════════════════════════════════════
Schedule::command('outbox:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/outbox-worker.log'));

// ═══════════════════════════════════════════════════════════════════════════════
// STORAGE MAINTENANCE - Data Hygiene (Prunable Models)
// ═══════════════════════════════════════════════════════════════════════════════
// Cleans Idempotency Keys (>48h) and Outbox Messages (Logic in Prunable).
// ═══════════════════════════════════════════════════════════════════════════════
Schedule::command('model:prune')->daily()->at('04:00');

// ═══════════════════════════════════════════════════════════════════════════════
// DATA LIFECYCLE MANAGEMENT - Idempotency Pruning (DBRE Mandate)
// ═══════════════════════════════════════════════════════════════════════════════
// Removes 'COMPLETED' idempotency records older than 30 days.
// Uses "Nibbling" strategy (chunked deletes) to prevent index locking.
// ═══════════════════════════════════════════════════════════════════════════════
Schedule::command('auth:prune-idempotency', ['--days=30', '--chunk=1000'])
    ->dailyAt('03:30') // Run before model:prune
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/idempotency-pruner.log'));

Schedule::command('ai:grant-monthly-credits')
    ->dailyAt('00:05')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-monthly-credit-grants.log'));

Schedule::command('ai:reconcile-reservations --older-than=60 --limit=500')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-reservation-reconciler.log'));

Schedule::command('dovra:ai-conversations-prune --limit=500')
    ->dailyAt('03:35')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-conversation-pruner.log'));

Schedule::command('ai:prune-executions --limit=1000')
    ->dailyAt('03:40')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-execution-pruner.log'));
Schedule::command('ai:prune-tool-confirmations --days=30 --limit=1000')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/ai-tool-confirmation-pruner.log'));

Schedule::command('analytics:reconcile-commercial-outcomes')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/commercial-outcome-reconciler.log'));

Schedule::command('dovra:discover-project')
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/dovra-discovery-projector.log'));

// ═══════════════════════════════════════════════════════════════════════════════
// APPOINTMENT REMINDERS - 24h Pre-Notification Engine
// ═══════════════════════════════════════════════════════════════════════════════
// Runs hourly to poll for appointments scheduled within the next 23–24 hours.
// Sends a reminder email to customers with valid customer_email addresses.
// Idempotent: Uses reminder_sent_at column to prevent duplicates.
// NOTE: Schedule::job dispatches to queue (async). Do NOT use runInBackground().
// ═══════════════════════════════════════════════════════════════════════════════
Schedule::job(new DispatchAppointmentReminders)
    ->hourly()
    ->withoutOverlapping();
