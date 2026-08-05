<?php

declare(strict_types=1);

use App\Broadcasting\ChannelRegistry;
use App\Infrastructure\Broadcasting\TenantChannelAuthorizer;
use App\Models\User;
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
Broadcast::channel(ChannelRegistry::TENANT_CHANNEL_PATTERN, function (User $user, string $id): bool {
    return app(TenantChannelAuthorizer::class)->canAccessTenant($user, $id);
});

/**
 * Tenant-scoped ADMIN-ONLY channel.
 *
 * SENSITIVE: Only for users with elevated privileges within the tenant.
 * Used for: NewLoginDetected, LowStock, Critical Alerts.
 */
Broadcast::channel(ChannelRegistry::TENANT_ADMIN_CHANNEL_PATTERN, function (User $user, string $id): bool {
    return app(TenantChannelAuthorizer::class)->canAccessTenantAdmin($user, $id);
});

/**
 * User-scoped private channel for personal notifications.
 *
 * Events using this channel:
 *   - NewLoginDetectedBroadcast
 */
Broadcast::channel(ChannelRegistry::USER_CHANNEL_PATTERN, function (User $user, string $id): bool {
    return (string) $user->id === (string) $id;
});

/**
 * Order-scoped channel for customer order tracking (future use).
 *
 * This allows customers to receive real-time updates about their specific order.
 *
 * SECURITY: Multi-tenant isolation enforced - user can only access orders
 * belonging to their tenant AND owned by them.
 */
Broadcast::channel(ChannelRegistry::ORDER_CHANNEL_PATTERN, function (User $user, string $id): bool {
    return app(TenantChannelAuthorizer::class)->canAccessOrder($user, $id);
});
