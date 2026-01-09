<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Identity;

use App\Domain\Identity\Events\TenantRegistered;
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
            'user_id' => $event->user->id,
            'tenant_id' => $event->tenant->id
        ]);

        // Dispatch the framework event
        event(new Registered($event->user));
    }
}
