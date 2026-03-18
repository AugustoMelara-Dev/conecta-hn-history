<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

/**
 * HasDomainEvents - Event Recording and Release Mechanism
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL PATTERN: Deferred Event Dispatch                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM:                                                                ║
 * ║    Dispatching events immediately when state changes can cause:         ║
 * ║    1. Events fired before transaction commits (phantom events)          ║
 * ║    2. Side effects executed even if operation fails                     ║
 * ║    3. Tight coupling between domain and event bus                       ║
 * ║                                                                          ║
 * ║  SOLUTION:                                                               ║
 * ║    Entity records events internally during mutations, but does NOT      ║
 * ║    dispatch them. The Repository is responsible for extracting and      ║
 * ║    dispatching events ONLY after successful persistence.                ║
 * ║                                                                          ║
 * ║  FLOW:                                                                   ║
 * ║    1. Domain: $entity->doSomething() → $this->record(new Event())       ║
 * ║    2. Repository: $entity->save() → $entity->releaseEvents()            ║
 * ║    3. Infrastructure: Event::dispatch($event)                           ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
// Used by models outside the Domain scan path.
// @phpstan-ignore trait.unused
trait HasDomainEvents
{
    /**
     * @var array<object> Domain events recorded during entity lifecycle
     */
    private array $domainEvents = [];

    /**
     * Record a domain event to be dispatched later.
     *
     * NOTE: This does NOT dispatch the event! It queues it for later release.
     *
     * @param  object  $event  The domain event to record
     */
    protected function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Get all recorded domain events.
     *
     * @return array<object>
     */
    public function getRecordedEvents(): array
    {
        return $this->domainEvents;
    }

    /**
     * Release and clear all recorded domain events.
     *
     * This should be called by the Repository AFTER successful persistence.
     * The Repository is then responsible for dispatching these events.
     *
     * @return array<object> The events that were recorded
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Check if there are any recorded events pending dispatch.
     */
    public function hasRecordedEvents(): bool
    {
        return count($this->domainEvents) > 0;
    }

    /**
     * Clear all recorded events without dispatching.
     *
     * Use this when you need to discard events (e.g., rollback scenario).
     */
    public function clearRecordedEvents(): void
    {
        $this->domainEvents = [];
    }
}
