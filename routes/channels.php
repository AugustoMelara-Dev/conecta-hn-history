<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// ═══════════════════════════════════════════════════════════════════════════════
// TENANT CHANNEL - Dashboard Real-Time Updates
// ═══════════════════════════════════════════════════════════════════════════════
// SECURITY: User must belong to the tenant to subscribe.
// USED BY: NewOrderReceived, OrderStatusUpdated
// ═══════════════════════════════════════════════════════════════════════════════
Broadcast::channel('tenant.{tenantId}', function (User $user, int $tenantId) {
    // User must be authenticated and belong to this tenant
    return $user->tenant_id === $tenantId;
});

// ═══════════════════════════════════════════════════════════════════════════════
// ORDER TRACKING CHANNEL - Customer Real-Time Updates
// ═══════════════════════════════════════════════════════════════════════════════
// SECURITY: Anyone with the order's tracking UUID can subscribe.
// This is secure because UUIDs are cryptographically random and non-guessable.
// USED BY: OrderStatusUpdated (customer tracking screen)
// ═══════════════════════════════════════════════════════════════════════════════
Broadcast::channel('order.{trackingUuid}', function ($user, string $trackingUuid) {
    // For customer tracking, we don't require authentication
    // The UUID itself acts as the security token (unguessable)
    // Verify the order exists with this UUID
    $exists = Order::where('tracking_uuid', $trackingUuid)->exists();
    
    if (! $exists) {
        Log::warning('WebSocket auth failed: Invalid tracking UUID', [
            'tracking_uuid' => $trackingUuid,
            'ip' => request()->ip(),
        ]);
    }
    
    return $exists;
});

// ═══════════════════════════════════════════════════════════════════════════════
// USER PRIVATE CHANNEL - Personal Notifications
// ═══════════════════════════════════════════════════════════════════════════════
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
