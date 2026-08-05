<?php

declare(strict_types=1);

namespace App\Listeners\Security;

use App\Events\Broadcast\NewLoginDetectedBroadcast;
use App\Events\Security\NewLoginDetected;
use App\Mail\Security\LoginAlertMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

final class SendLoginAlertListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(NewLoginDetected $event): array
    {
        // UUID STRING: Use toString() for tenant context
        return [
            new \App\Infrastructure\Shared\Middleware\TenantQueueMiddleware($event->tenantId()?->toString()),
        ];
    }

    /**
     * Handle the event.
     */
    public function handle(NewLoginDetected $event): void
    {
        // 1. Resolve User Model (needed for Mail)
        $user = User::find($event->userId());

        if ($user) {
            // Send Email Alert
            Mail::to($user->email)->send(
                new LoginAlertMail($user, $event->ipAddress()->toString(), $event->occurredAt())
            );
        }

        // 2. Dispatch Real-Time Broadcast (Only if Tenant Context Exists)
        if ($tenantId = $event->tenantId()) {
            NewLoginDetectedBroadcast::fromDomainData(
                userId: $event->userId(),
                tenantId: $tenantId->toString(),
                userEmail: $event->userEmail(),
                ipAddress: $event->ipAddress()->toString(),
                userAgent: $event->userAgent(),
                loginAt: $event->occurredAt()
            )->dispatch();
        }
    }
}
