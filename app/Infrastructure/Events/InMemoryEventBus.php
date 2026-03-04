<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Contracts\Events\DomainEventBus;

/**
 * InMemoryEventBus - Testing Implementation of DomainEventBus
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TESTING INFRASTRUCTURE: For Pure Domain Unit Tests                     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  USAGE IN TESTS:                                                        ║
 * ║                                                                          ║
 * ║    $eventBus = new InMemoryEventBus();                                  ║
 * ║    $service = new OrderStateService($eventBus);                         ║
 * ║                                                                          ║
 * ║    $service->cancel($order, 'test');                                    ║
 * ║                                                                          ║
 * ║    $this->assertTrue($eventBus->hasPublished(OrderCancelled::class));   ║
 * ║    $this->assertCount(1, $eventBus->getPublished());                    ║
 * ║                                                                          ║
 * ║  BENEFITS:                                                              ║
 * ║    - No Laravel bootstrapping needed                                    ║
 * ║    - No database, no queue, no side effects                            ║
 * ║    - Millisecond-fast unit tests                                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class InMemoryEventBus implements DomainEventBus
{
    /** @var array<int, DomainEvent> */
    private array $publishedEvents = [];

    /**
     * Record the event without actually dispatching.
     */
    public function publish(DomainEvent $event): void
    {
        $this->publishedEvents[] = $event;
    }

    /**
     * Record multiple events.
     */
    public function publishMany(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->publishedEvents[] = $event;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Test Assertions
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get all published events.
     *
     * @return array<int, DomainEvent>
     */
    public function getPublished(): array
    {
        return $this->publishedEvents;
    }

    /**
     * Check if an event of the given type was published.
     *
     * @param  class-string<DomainEvent>  $eventClass
     */
    public function hasPublished(string $eventClass): bool
    {
        foreach ($this->publishedEvents as $event) {
            if ($event instanceof $eventClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first event of a given type.
     *
     * @template T of DomainEvent
     *
     * @param  class-string<T>  $eventClass
     * @return T|null
     */
    public function getFirst(string $eventClass): ?DomainEvent
    {
        foreach ($this->publishedEvents as $event) {
            if ($event instanceof $eventClass) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Clear all recorded events.
     */
    public function reset(): void
    {
        $this->publishedEvents = [];
    }

    /**
     * Assert a specific event was published with matching properties.
     *
     * @param  class-string<DomainEvent>  $eventClass
     * @param  array<string, mixed>  $properties
     */
    public function assertPublished(string $eventClass, array $properties = []): void
    {
        $event = $this->getFirst($eventClass);

        if ($event === null) {
            throw new \RuntimeException("Event {$eventClass} was not published");
        }

        foreach ($properties as $property => $expected) {
            $actual = $event->{$property} ?? null;
            if ($actual !== $expected) {
                throw new \RuntimeException(
                    "Event {$eventClass}::{$property} expected {$expected}, got {$actual}"
                );
            }
        }
    }
}
