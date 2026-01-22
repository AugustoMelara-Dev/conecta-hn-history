<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Projections;

use App\Domain\Identity\Events\UserRegistered;
use Illuminate\Events\Dispatcher;

/**
 * ProjectionEventSubscriber - The Synchronous Bridge
 *
 * Intercepts Domain Events synchronously and delegates WRITES (SQL) to Projectors.
 * Laws:
 * 1. Synchronous (No Queues): Instant Consistency.
 * 2. Proxy Rule: No Business Logic.
 */
class ProjectionEventSubscriber
{
    /**
     * @param  UserProjector  $userProjector  Injected Projector (The Worker)
     */
    public function __construct(
        private readonly UserProjector $userProjector
    ) {}

    /**
     * Handle UserRegistered Event.
     */
    public function handleUserRegistered(UserRegistered $event): void
    {
        // DELEGATION (The Proxy Rule)
        // No Try/Catch: Failures must abort the Transaction.
        $this->userProjector->project($event);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserRegistered::class => 'handleUserRegistered',
            // Future: UserRoleChanged::class => 'handleUserRoleChanged',
        ];
    }
}
