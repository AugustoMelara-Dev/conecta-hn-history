<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts\Events;

/**
 * W-06 P1 Phase 1: Canonical Domain contract.
 *
 * App\Contracts\Events\DomainEvent is the current implementation source.
 * Migration path:
 *   Phase 2 — Update all `implements DomainEvent` in Domain to use this namespace.
 *   Phase 3 — Migrate InMemoryEventBus + LaravelEventBus.
 *   Phase 4 — Delete App\Contracts\Events\DomainEvent.
 */
interface DomainEvent extends \App\Contracts\Events\DomainEvent
{
}
