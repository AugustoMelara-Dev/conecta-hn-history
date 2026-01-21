<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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
Schedule::job(new \App\Infrastructure\Jobs\Governance\ReleaseStaleOrders)
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


