<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * EventBusInterface - Application Port
 *
 * Defines the contract for dispatching events without coupling to a specific
 * infrastructure implementation (Laravel Dispatcher, Redis, Kafka).
 */
interface EventBusInterface
{
    /**
     * Dispatch an event to the underlying bus.
     */
    public function dispatch(object $event): void;
}
