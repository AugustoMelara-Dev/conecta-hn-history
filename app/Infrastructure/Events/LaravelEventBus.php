<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Contracts\Events\DomainEvent;
use App\Contracts\Events\DomainEventBus;

/**
 * LaravelEventBus - Laravel Implementation of DomainEventBus
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE LAYER: Bridges Domain → Laravel Event System            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  KEY POINT: This class is the ONLY place that knows about Laravel's     ║
 * ║  event() helper. Domain services don't know Laravel exists.             ║
 * ║                                                                          ║
 * ║  TRANSACTION SAFETY:                                                    ║
 * ║    Since DomainEvent extends ShouldDispatchAfterCommit, Laravel         ║
 * ║    automatically defers the actual dispatch until DB commits.           ║
 * ║                                                                          ║
 * ║  SWAP EXAMPLE:                                                          ║
 * ║    For Kafka, create KafkaEventBus implementing DomainEventBus          ║
 * ║    and bind it in AppServiceProvider. Zero domain code changes.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LaravelEventBus implements DomainEventBus
{
    /**
     * Publish a single domain event via Laravel's event dispatcher.
     */
    public function publish(DomainEvent $event): void
    {
        event($event);
    }

    /**
     * Publish multiple events atomically.
     *
     * NOTE: Laravel doesn't have native atomic multi-event publishing.
     * If you need true atomicity, consider wrapping in a DB transaction
     * at the Application Service layer, not here.
     */
    public function publishMany(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            event($event);
        }
    }
}
