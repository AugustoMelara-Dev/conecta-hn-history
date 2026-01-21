<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Domain\Shared\Contracts\EventDispatcherInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * TransactionalEventDispatcher - Outbox Pattern Implementation
 *
 * Decorates the default Laravel dispatcher to ensure events are effectively
 * "dispatched" only when the transaction commits, preserving consistency.
 */
final readonly class TransactionalEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
        private \App\Domain\Shared\OutboxRepositoryInterface $outboxRepository
    ) {}

    /**
     * Dispatch an event with transactional safety.
     *
     * @param object $event
     * @return void
     */
    public function dispatch(object $event): void
    {
        // 1. PERSIST TO OUTBOX (Atomic with Transaction)
        // This guarantees the event exists if and only if the transaction commits.
        // We do NOT dispatch via memory here. We rely entirely on the
        // background worker to read this table and dispatch.
        // This eliminates the "Phantom Write" race condition where
        // commit succeeds but process dies before afterCommit fires.
        $this->outboxRepository->append($event);
    }

    /**
     * Fallback to standard dispatch (e.g. for non-transactional contexts)
     */
    public function dispatchNow(object $event): void
    {
        $this->outboxRepository->append($event);
        $this->dispatcher->dispatch($event);
    }
}
