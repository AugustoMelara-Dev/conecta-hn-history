<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;

/**
 * TenantAwareEvent - Trait for Queue-Safe Multi-Tenant Event Serialization
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: WHY SerializesModels FAILS IN MULTI-TENANCY                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THE RACE CONDITION:                                                    ║
 * ║    1. Event dispatched: new NewOrderReceived($order)                    ║
 * ║    2. SerializesModels saves: {"order_id": 5, "class": "Order"}         ║
 * ║    3. Queue Worker picks up job                                         ║
 * ║    4. PHP tries to unserialize: Order::find(5)                          ║
 * ║    5. BUT: Worker is connected to CENTRAL database!                     ║
 * ║    6. CRASH: ModelNotFoundException (Order 5 doesn't exist in Central) ║
 * ║                                                                          ║
 * ║  THE SOLUTION:                                                          ║
 * ║    Events must carry $tenantId as PRIMITIVE (not model reference).      ║
 * ║    Queue Middleware reads $tenantId FIRST, switches DB, THEN model      ║
 * ║    deserialization happens in correct tenant context.                   ║
 * ║                                                                          ║
 * ║  USAGE:                                                                 ║
 * ║    class MyEvent implements ShouldQueue {                               ║
 * ║        use Dispatchable, SerializesModels, TenantAwareEvent;            ║
 * ║        public function __construct(Order $order) {                      ║
 * ║            $this->setTenantContext($order->tenant_id);                  ║
 * ║        }                                                                ║
 * ║    }                                                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
trait TenantAwareEvent
{
    /**
     * Tenant ID for Queue Worker context switching.
     *
     * @notice This MUST be a primitive (int/string), NOT a model.
     *         It's read by TenantQueueMiddleware BEFORE model deserialization.
     */
    public int $tenantId;

    /**
     * Set tenant context during event construction.
     */
    protected function setTenantContext(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Get tenant ID for Queue Middleware.
     */
    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    /**
     * Restore tenant context (called by Queue Middleware before deserialization).
     */
    public static function restoreTenantContext(int $tenantId): void
    {
        // Find and activate tenant
        $tenant = Tenant::find($tenantId);

        if ($tenant) {
            // If using Spatie Laravel-Multitenancy
            if (method_exists($tenant, 'makeCurrent')) {
                $tenant->makeCurrent();
            }

            // If using manual tenant switching
            // config(['database.default' => 'tenant_' . $tenantId]);
            // DB::purge('tenant');
            // DB::reconnect('tenant');
        }
    }
}
