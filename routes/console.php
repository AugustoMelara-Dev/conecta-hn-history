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
Schedule::job(new \App\Jobs\ReleaseStaleOrders)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

