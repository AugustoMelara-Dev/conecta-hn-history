<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AuditTenantLimits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting AuditTenantLimits job...');

        // 1. Get all non-PRO tenants
        $freeTenants = Tenant::where('is_pro', false)->get();

        foreach ($freeTenants as $tenant) {
            // 2. Enforce Product Limit (Soft Lock)
            $products = $tenant->products()
                ->orderBy('created_at', 'desc')
                ->get();

            if ($products->count() > 10) {
                // Keep the 10 most recent active
                $allowedProducts = $products->take(10);
                $lockedProducts = $products->slice(10);

                // Ensure allowed are unlocked (optional, maybe we don't want to auto-unlock)
                // $allowedProducts->each(function ($product) {
                //     $product->update(['is_locked' => false]);
                // });

                // Lock the rest
                foreach ($lockedProducts as $product) {
                    if ($product->is_visible || ! $product->is_locked) {
                        $product->update([
                            'is_visible' => false,
                            'is_locked' => true,
                        ]);
                        Log::info("Locked product {$product->id} for tenant {$tenant->id}");
                    }
                }
            }

            // 3. Reset Visual Customizations (Branding Reset)
            if ($tenant->primary_color || $tenant->banner_path) {
                $tenant->update([
                    'primary_color' => null,
                    'banner_path' => null,
                ]);
                Log::info("Reset branding for tenant {$tenant->id}");
            }
        }

        Log::info('AuditTenantLimits job completed.');
    }
}
