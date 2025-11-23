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
     * 
     * SOFT-LOCKING Logic:
     * 1. Iterate all tenants
     * 2. Count products (without scopes)
     * 3. If count > product_limit, lock oldest products
     * 4. If count <= product_limit, unlock all products
     */
    public function handle(): void
    {
        $tenants = Tenant::withCount(['products' => function ($query) {
            $query->withoutGlobalScopes();
        }])->get();

        foreach ($tenants as $tenant) {
            $this->auditTenant($tenant);
        }

        Log::info('AuditTenantLimits completed', [
            'tenants_audited' => $tenants->count(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Audit individual tenant's product limit
     */
    protected function auditTenant(Tenant $tenant): void
    {
        $limit = $tenant->product_limit ?? 10; // Default to 10 if NULL
        
        // Special case: NULL or 0 = unlimited (optional bypass)
        if ($limit === null || $limit === 0) {
            // Unlock all products for unlimited plan
            $tenant->products()->withoutGlobalScopes()->update(['is_locked' => false]);
            return;
        }

        $products = $tenant->products()->withoutGlobalScopes()->get();
        $productCount = $products->count();

        if ($productCount > $limit) {
            // Exceeds limit: Lock oldest products
            $excessCount = $productCount - $limit;
            
            // Get oldest products (by created_at)
            $productsToLock = $tenant->products()
                ->withoutGlobalScopes()
                ->where('is_locked', false)
                ->orderBy('created_at', 'asc')
                ->limit($excessCount)
                ->get();

            foreach ($productsToLock as $product) {
                $product->update(['is_locked' => true]);
            }

            Log::warning('Tenant product limit exceeded', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'product_count' => $productCount,
                'limit' => $limit,
                'locked_count' => $excessCount,
            ]);
        } else {
            // Under limit: Unlock all products
            $tenant->products()->withoutGlobalScopes()->update(['is_locked' => false]);
            
            if ($productCount < $limit) {
                Log::info('Tenant under product limit', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'product_count' => $productCount,
                    'limit' => $limit,
                    'available_slots' => $limit - $productCount,
                ]);
            }
        }
    }
}
