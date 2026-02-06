<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Identity;

use App\Domain\Identity\Events\TenantRegistered;
use App\Models\User; // Added for fetching
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

/**
 * BridgeLaravelAuthEvents
 *
 * INFRASTRUCTURE BRIDGE: Connects pure Domain Events to Laravel's Auth System.
 * This allows us to keep the Domain clean while still leveraging Laravel features
 * like email verification.
 */
final class BridgeLaravelAuthEvents
{
    /**
     * Handle the pure domain event.
     */
    public function handle(TenantRegistered $event): void
    {
        Log::info('Bridge: Mapping TenantRegistered to Laravel Registered event', [
            'user_id' => $event->userId,
            'tenant_id' => $event->tenantId,
        ]);

        $user = User::find($event->userId);

        if ($user) {
            // Dispatch the framework event
            event(new Registered($user));
        }
    }
}
