<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence\Decorators;

use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Support\Str;

/**
 * SchemaValidatingOutboxRepository
 *
 * Decorator that acts as a Firewall.
 * Prevents "poison" (malformed events) from entering the Outbox.
 */
final readonly class SchemaValidatingOutboxRepository implements OutboxRepositoryInterface
{
    public function __construct(
        private SchemaValidatorInterface $validator
    ) {}

    public function save(DomainEventMessage $message): void
    {
        // 1. Resolve Subject Name
        // Convention: 'App\Domain\Auth\Events\UserRegistered' -> 'user.registered.v1'
        // This mapping strategy needs to be robust. For now, we use a static mapper or heuristic.
        // Assuming the Event Class Name or Type carries the version or we default to v1.

        $subject = $this->resolveSubject($message->type);

        if ($subject) {
            // 2. Decode Payload (it's stored as JSON string in DomainEventMessage)
            $payload = json_decode($message->payload, true);

            // 3. Validate
            $this->validator->validate($subject, $payload);
        }

        $decodedPayload = json_decode($message->payload, true);
        if (! is_array($decodedPayload)) {
            $decodedPayload = ['data' => $message->payload];
        }

        OutboxMessage::query()->create([
            'id' => $message->eventId ?: (string) Str::ulid(),
            'type' => $message->type,
            'aggregate_id' => $message->aggregateId,
            'partition_key' => abs(crc32((string) $message->aggregateId)) % OutboxMessage::PARTITION_COUNT,
            'sequence_number' => 0,
            'payload' => [
                'meta' => $message->metadata,
                'data' => $decodedPayload,
            ],
            'occurred_at' => $message->occurredAt,
            'status' => OutboxMessage::STATUS_PENDING,
            'attempts' => 0,
            'recovery_count' => 0,
        ]);
    }

    private function resolveSubject(string $eventType): ?string
    {
        // Heuristic: ClassName to dot.notation
        // App\Domain\Auth\Events\UserRegistered -> user.registered.v1
        // We can optimize this with a Map/Config later.

        $className = class_basename($eventType); // UserRegistered
        $kebab = Str::kebab($className); // user-registered
        $dot = str_replace('-', '.', $kebab); // user.registered

        // Check if a specific version exists?
        // ideally the DomainEventMessage should carry the version.
        // For this iteration, we enforce v1.

        return $dot.'.v1';
    }
}
