<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\EventStore;

/**
 * ProjectorInterface - The Read Model Builder Contract
 * 
 * Enforces determinism in projecting events to SQL.
 */
interface ProjectorInterface
{
    /**
     * Determine if the projector supports the given event.
     */
    public function supports(object $event): bool;

    /**
     * Project the event state into the Read Model.
     * 
     * @param object $event Domain Event
     */
    public function project(object $event): void;

    /**
     * Reset the projection (Truncate table).
     * Used before a Full Replay.
     */
    public function reset(): void;
}
