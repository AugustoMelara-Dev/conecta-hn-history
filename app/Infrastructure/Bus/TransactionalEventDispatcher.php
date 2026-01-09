<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * TransactionalEventDispatcher - Outbox Pattern Implementation
 *
 * Decorates the default Laravel dispatcher to ensure events are effectively
 * "dispatched" only when the transaction commits, preserving consistency.
 */
final readonly class TransactionalEventDispatcher
{
    public function __construct(
        private Dispatcher $dispatcher
    ) {}

    /**
     * Dispatch an event with transactional safety.
     *
     * @param object $event
     * @return void
     */
    public function dispatch(object $event): void
    {
        // Ideally, here we would inspect if the event implements ShouldBeStoredInOutbox
        // and serialize it to a DB table 'outbox_events' if so.
        // For 'Operation Unified Command' Phase 1, we rely on 'dispatchAfterCommit'.
        
        // This leverages Laravel's native transactional awareness for models/events
        // but explicit usage here standardizes the behavior for Domain Events.
        
        DB::afterCommit(function () use ($event) {
            $this->dispatcher->dispatch($event);
        });
    }

    /**
     * Fallback to standard dispatch (e.g. for non-transactional contexts)
     */
    public function dispatchNow(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
