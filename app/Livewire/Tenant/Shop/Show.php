<?php

namespace App\Livewire\Tenant\Shop;

use App\Models\Tenant;
use App\Models\TenantVisit;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public Storefront - Customer-facing shop page
 *
 * Features:
 * - Display tenant info and products
 * - Track visits with throttle (1 per hour)
 * - PRO tenants get cart functionality
 * - FREE tenants show catalog-only mode
 */
#[Layout('layouts.shop')]
class Show extends Component
{
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->recordVisit();
    }

    /**
     * Record visit with simple throttle mechanism
     */
    protected function recordVisit(): void
    {
        $cookieName = 'visited_'.$this->tenant->id;

        if (! Cookie::get($cookieName)) {
            TenantVisit::create([
                'tenant_id' => $this->tenant->id,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'visited_at' => now(),
            ]);

            // Queue cookie for 60 minutes
            Cookie::queue($cookieName, true, 60);
        }
    }

    public function render()
    {
        // Eager load products - filter active only
        $products = $this->tenant->products()
            ->where('is_visible', true)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.tenant.shop.show', [
            'products' => $products,
        ])->title($this->tenant->name.' | Conecta HN');
    }
}
