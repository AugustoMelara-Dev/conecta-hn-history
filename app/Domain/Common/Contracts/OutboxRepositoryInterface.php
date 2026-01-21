<?php

declare(strict_types=1);

namespace App\Domain\Common\Contracts;

/**
 * OutboxRepositoryInterface - Transactional Event Storage
 *
 * CRITICAL FIX (P0): Added explicit import of DomainEventInterface.
 * Without this import, PHP cannot resolve the type hint and will
 * throw a fatal error at runtime.
 *
 * This interface enables the Transactional Outbox Pattern:
 * - Events are persisted in the same transaction as business data
 * - Guarantees atomic consistency (all-or-nothing)
 * - A separate worker polls the outbox and dispatches events
 */
interface OutboxRepositoryInterface
{
    /**
     * Append a domain event to the outbox for reliable delivery.
     *
     * MUST be called within the same database transaction as the aggregate save.
     * Failure to do so breaks the atomicity guarantee.
     *
     * @param  DomainEventInterface  $event  The domain event to persist
     */
    public function append(DomainEventInterface $event): void;
}
