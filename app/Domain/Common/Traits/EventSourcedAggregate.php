<?php

declare(strict_types=1);

namespace App\Domain\Common\Traits;

use ReflectionClass;

/**
 * EventSourcedAggregate - The Behavior Engine
 * 
 * Centralizes Event Sourcing plumbing: recording, applying, and reconstituting.
 */
trait EventSourcedAggregate
{
    /** @var array<object> */
    private array $recordedEvents = [];

    protected int $version = 0;

    /**
     * Records a new domain event and applies it immediately.
     */
    protected function recordThat(object $event): void
    {
        $this->recordedEvents[] = $event;
        $this->version++;
        $this->apply($event);
    }

    /**
     * Mutations Router.
     * Routes events to 'apply{EventName}' methods.
     */
    protected function apply(object $event): void
    {
        $eventClass = (new ReflectionClass($event))->getShortName();
        $method = 'apply' . $eventClass;

        if (method_exists($this, $method)) {
            $this->$method($event);
        }
    }

    /**
     * Returns pending events and flushes memory.
     * Used by Repository.
     */
    public function getUncommittedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    /**
     * Rebuilds the aggregate from history.
     */
    public static function reconstituteFromHistory(array $events): static
    {
        // Bypass constructor to create empty shell
        $instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        
        foreach ($events as $event) {
            $instance->apply($event);
            // We assume history events increment version too?
            // Usually yes, to track optimistic concurrency.
            $instance->version++; 
        }

        return $instance;
    }

    public function version(): int
    {
        return $this->version;
    }
}
