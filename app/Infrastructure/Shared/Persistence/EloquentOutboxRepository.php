<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence;

use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use Illuminate\Support\Facades\DB;

/**
 * EloquentOutboxRepository
 *
 * Infrastructure Adapter for persisting domain events using Laravel's DB query builder.
 * HIDDEN BEHIND PORT: Application layer never sees this class.
 */
class EloquentOutboxRepository implements OutboxRepositoryInterface
{
    public function save(DomainEventMessage $message): void
    {
        DB::table('domain_outbox')->insert([
            'id' => $message->eventId,
            'type' => $message->type,
            'aggregate_id' => $message->aggregateId,
            'payload' => $message->payload,
            'occurred_at' => $message->occurredAt,
            'metadata' => json_encode($message->metadata),
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
