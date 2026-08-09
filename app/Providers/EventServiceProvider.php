<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Billing\Listeners\ProcessPaymentWebhookReceived;
use App\Application\Billing\Listeners\PromotePaymentEvidence;
use App\Domain\Billing\Events\PaymentReported;
use App\Domain\Billing\Events\PaymentWebhookReceived;
use App\Domain\Identity\Events\TenantRegistered;
use App\Domain\User\Events\UserProfileUpdated;
use App\Infrastructure\Identity\Projections\ProjectionEventSubscriber;
use App\Infrastructure\Listeners\Identity\BridgeLaravelAuthEvents;
use App\Infrastructure\User\Listeners\SendProfileUpdateNotificationListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event processing subscribers for the application.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        ProjectionEventSubscriber::class,
    ];

    /**
     * The event listener mappings for the application.
     *
     * Mapped for Zero Downtime Migration (V1 -> V2).
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [

        // Canonical (V2)
        UserProfileUpdated::NAME => [
            SendProfileUpdateNotificationListener::class,
        ],

        TenantRegistered::class => [
            BridgeLaravelAuthEvents::class,
        ],
        // BILLING MODULE (Level 27 - Wired)
        PaymentReported::class => [
            PromotePaymentEvidence::class,
        ],
        PaymentWebhookReceived::class => [
            ProcessPaymentWebhookReceived::class,
        ],

        /**
         * @deprecated LEGACY ALIAS SUPPORT (V1 -> V2 MIGRATION)
         * Policy: Monitor application logs for event tag 'auth.user_profile_updated'.
         * Action: Remove this mapping entry when traffic = 0 for 30 consecutive days.
         * Creation Date: 2026-01-17
         */
        UserProfileUpdated::LEGACY_NAME => [
            SendProfileUpdateNotificationListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
