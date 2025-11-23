<?php

namespace App\Console\Commands;

use App\Jobs\AuditTenantLimits;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired subscriptions and downgrade tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired subscriptions...');

        // Find PRO tenants with expired subscriptions
        // Assuming 'plan_expires_at' is the column name based on Tenant model
        // And 'is_pro' is true
        Tenant::where('is_pro', true)
            ->where('plan_expires_at', '<', now())
            ->chunk(100, function ($tenants) {
                foreach ($tenants as $tenant) {
                    $this->info("Downgrading tenant: {$tenant->name} (ID: {$tenant->id})");

                    // Downgrade logic
                    $tenant->update([
                        'is_pro' => false,
                        'product_limit' => 10, // System default
                    ]);

                    // Trigger AuditTenantLimits job immediately
                    AuditTenantLimits::dispatch($tenant);
                    
                    $this->info("  -> Downgraded and audit job dispatched.");
                }
            });

        $this->info('Subscription expiration check completed.');
    }
}
