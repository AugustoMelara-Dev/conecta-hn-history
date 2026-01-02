<?php

declare(strict_types=1);

use App\Broadcasting\ChannelRegistry;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels - Single Source of Truth Integration
|--------------------------------------------------------------------------
|
| All channel authorization is synchronized with ChannelRegistry.
| This ensures that channel naming in events and authorization are identical.
|
| If you need to change channel naming conventions, ONLY modify ChannelRegistry.
| These routes will automatically use the new patterns.
|
*/

/**
 * Tenant-scoped private channel for multi-tenant broadcasting.
 *
 * Events using this channel:
 *   - OrderPaidBroadcast
 *   - OrderCancelledBroadcast
 *   - OrderShippedBroadcast
 *   - OrderRefundedBroadcast
 *   - LowStockBroadcast
 *   - NewLoginDetectedBroadcast (also broadcasts here for admins)
 */
Broadcast::channel(ChannelRegistry::TENANT_CHANNEL_PATTERN, function ($user, $id) {
    return $user->tenant_id === (int) $id;
});

/**
 * Tenant-scoped ADMIN-ONLY channel.
 *
 * SENSITIVE: Only for users with elevated privileges within the tenant.
 * Used for: NewLoginDetected, LowStock, Critical Alerts.
 */
Broadcast::channel(ChannelRegistry::TENANT_ADMIN_CHANNEL_PATTERN, function ($user, $id) {
    // 1. Must belong to the tenant
    if ($user->tenant_id !== (int) $id) {
        return false;
    }

    // 2. Must be an administrator or owner
    // Assuming 'seller' is the tenant owner role or 'super_admin' is system god
    return in_array($user->role, ['seller', 'super_admin'], true);
});

/**
 * User-scoped private channel for personal notifications.
 *
 * Events using this channel:
 *   - NewLoginDetectedBroadcast
 */
Broadcast::channel(ChannelRegistry::USER_CHANNEL_PATTERN, function ($user, $id) {
    return $user->id === (int) $id;
});

/**
 * Order-scoped channel for customer order tracking (future use).
 *
 * This allows customers to receive real-time updates about their specific order.
 *
 * SECURITY: Multi-tenant isolation enforced - user can only access orders
 * belonging to their tenant AND owned by them.
 */
Broadcast::channel(ChannelRegistry::ORDER_CHANNEL_PATTERN, function ($user, $id) {
    // CRITICAL: Enforce tenant isolation to prevent cross-tenant order access
    $customerId = \App\Models\Order::where('id', $id)
        ->where('tenant_id', $user->tenant_id)  // Multi-tenant isolation
        ->value('customer_id');

    return $customerId !== null && $user->id === $customerId;
});
