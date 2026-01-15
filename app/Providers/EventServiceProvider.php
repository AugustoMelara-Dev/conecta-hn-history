<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Infrastructure\Identity\Projections\ProjectionEventSubscriber;

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
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
