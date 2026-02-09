<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Identity\Projections\ProjectionEventSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event processing subscribers for the application.
     *
     * @var array
     */
    protected $subscribe = [
        ProjectionEventSubscriber::class,
    ];

    /**
     * The event listener mappings for the application.
     *
     * Mapped for Zero Downtime Migration (V1 -> V2).
     *
     * @var array
     */
    protected $listen = [
        // Canonical (V2)
        \App\Domain\User\Events\UserProfileUpdated::NAME => [
            \App\Infrastructure\User\Listeners\SendProfileUpdateNotificationListener::class,
        ],

        // BILLING MODULE (Level 27 - Wired)
        \App\Domain\Billing\Events\PaymentReported::class => [
            \App\Application\Billing\Listeners\PromotePaymentEvidence::class,
        ],

        // APPOINTMENT NOTIFICATIONS
        \App\Events\Appointment\AppointmentCreated::class => [
            \App\Listeners\SendAppointmentConfirmation::class,
        ],

        /**
         * @deprecated LEGACY ALIAS SUPPORT (V1 -> V2 MIGRATION)
         * Policy: Monitor application logs for event tag 'auth.user_profile_updated'.
         * Action: Remove this mapping entry when traffic = 0 for 30 consecutive days.
         * Creation Date: 2026-01-17
         */
        \App\Domain\User\Events\UserProfileUpdated::LEGACY_NAME => [
            \App\Infrastructure\User\Listeners\SendProfileUpdateNotificationListener::class,
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
