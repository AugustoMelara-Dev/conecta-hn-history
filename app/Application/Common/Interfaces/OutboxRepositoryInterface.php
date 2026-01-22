<?php

declare(strict_types=1);

namespace App\Application\Common\Interfaces;

/**
 * OutboxRepositoryInterface
 *
 * Manages the transactional storage of domain events to ensure at-least-once delivery.
 */
interface OutboxRepositoryInterface
{
    /**
     * Store a domain event in the outbox table.
     * Must be called within the same database transaction as the business logic.
     *
     * @param  object  $event  The Domain Event (should be serializable)
     */
    public function store(object $event): void;
}
