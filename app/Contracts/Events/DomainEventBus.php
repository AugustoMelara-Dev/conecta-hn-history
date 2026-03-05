<?php

declare(strict_types=1);

namespace App\Contracts\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;

/**
 * DomainEventBus - Infrastructure Contract for Event Publishing
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL ARCHITECTURE: Domain ↔ Infrastructure Decoupling             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WHY THIS EXISTS:                                                       ║
 * ║                                                                          ║
 * ║  BEFORE (Coupled to Laravel):                                           ║
 * ║    OrderCancelled::create(...)->dispatch();                             ║
 * ║    // Event knows about Laravel's event dispatcher                      ║
 * ║    // Domain services depend on Illuminate\Events                       ║
 * ║    // Testing requires Laravel bootstrapping                            ║
 * ║                                                                          ║
 * ║  AFTER (Decoupled via Bus):                                             ║
 * ║    $this->eventBus->publish(OrderCancelled::create(...));               ║
 * ║    // Domain service depends only on this interface                     ║
 * ║    // Can swap Laravel for Kafka/RabbitMQ/AMQP                         ║
 * ║    // Pure unit testing with mock EventBus                             ║
 * ║                                                                          ║
 * ║  IMPLEMENTATION RULE:                                                   ║
 * ║    - Domain Services inject DomainEventBus                             ║
 * ║    - Application Layer provides LaravelEventBus implementation         ║
 * ║    - Events NEVER call ->dispatch() on themselves                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * IMPLEMENTATIONS:
 *   - LaravelEventBus: Default, wraps event() helper
 *   - InMemoryEventBus: For unit testing
 *   - KafkaEventBus: Future, for event sourcing at scale
 */
interface DomainEventBus
{
    /**
     * Publish a domain event.
     *
     * The implementation decides HOW to dispatch (sync, async, Kafka, etc.).
     * The domain service does NOT know about the underlying mechanism.
     *
     * @param  DomainEvent  $event  The domain event to publish
     */
    public function publish(DomainEvent $event): void;

    /**
     * Publish multiple domain events atomically.
     *
     * Used when a single domain action produces multiple events.
     * Implementations should ensure all-or-nothing semantics.
     *
     * @param  DomainEvent  ...$events  The domain events to publish
     */
    public function publishMany(DomainEvent ...$events): void;
}
