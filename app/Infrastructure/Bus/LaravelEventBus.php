<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Application\Shared\Contracts\EventBusInterface;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * LaravelEventBus - Infrastructure Adapter
 *
 * Wraps the Laravel Event Dispatcher to satisfy the Application Port.
 */
final class LaravelEventBus implements EventBusInterface
{
    public function __construct(
        private readonly Dispatcher $laravelDispatcher
    ) {}

    public function dispatch(object $event): void
    {
        $this->laravelDispatcher->dispatch($event);
    }
}
