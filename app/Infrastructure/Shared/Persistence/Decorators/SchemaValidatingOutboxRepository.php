<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Persistence\Decorators;

use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Shared\Contracts\SchemaValidatorInterface;
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
        private OutboxRepositoryInterface $inner,
        private SchemaValidatorInterface $validator
    ) {}

    public function append(object $event): void
    {
        // 1. Resolve Subject Name from event class
        $subject = $this->resolveSubject(get_class($event));

        if ($subject) {
            // 2. Extract public properties for validation
            $reflection = new \ReflectionClass($event);
            $payload = [];
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $payload[$property->getName()] = $property->getValue($event);
            }

            // 3. Validate
            $this->validator->validate($subject, $payload);
        }

        // 4. Delegate to Inner Repository
        $this->inner->append($event);
    }

    public function retrieveNextStaleBatch(int $batchSize, int $staleThresholdSeconds, array $ownedPartitionKeys): \Illuminate\Support\Collection
    {
        // Pure delegation — schema validation is only for writes
        return $this->inner->retrieveNextStaleBatch($batchSize, $staleThresholdSeconds, $ownedPartitionKeys);
    }

    private function resolveSubject(string $eventType): ?string
    {
        // Heuristic: ClassName to dot.notation
        // App\Domain\Auth\Events\UserRegistered -> user.registered.v1
        $className = class_basename($eventType);
        $kebab = Str::kebab($className);
        $dot = str_replace('-', '.', $kebab);

        return $dot.'.v1';
    }
}

