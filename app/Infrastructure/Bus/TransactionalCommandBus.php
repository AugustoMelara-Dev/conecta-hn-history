<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\SensitiveDataInterface;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * TransactionalCommandBus - Atomic Outbox Decorator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OUTBOX PATTERN: Couples command execution with audit persistence      ║
 * ║  in a single DB transaction — no Ghost Events, no Liar Events.        ║
 * ║  PII SAFE: Redacts SensitiveDataInterface properties before storage.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Wraps the native Laravel Bus dispatcher. The original (un-redacted) command
 * is always dispatched to the handler; only the outbox payload is sanitized.
 */
final class TransactionalCommandBus implements CommandBusInterface
{
     public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ConnectionInterface $connection,
        private readonly \App\Application\Shared\Contracts\IdGeneratorInterface $idGenerator,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function dispatch(object $command): mixed
    {
        $redactedPayload = $this->buildRedactedPayload($command);

        return $this->connection->transaction(function () use ($command, $redactedPayload) {
            // 1. Persist redacted command to outbox (PENDING)
            $outboxId = $this->idGenerator->generate();

            $this->connection->table('outbox_messages')->insert([
                'id' => $outboxId,
                'type' => get_class($command),
                'payload' => json_encode($redactedPayload, JSON_THROW_ON_ERROR),
                'status' => 'PENDING',
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Execute original (un-redacted) command
            $result = $this->dispatcher->dispatchSync($command);

            // 3. Mark as PUBLISHED on success (within same transaction)
            $this->connection->table('outbox_messages')
                ->where('id', $outboxId)
                ->update([
                    'status' => 'PUBLISHED',
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);

            return $result;
        });
    }

    /**
     * Build a redacted associative array from the command's public properties.
     *
     * If the command implements SensitiveDataInterface, properties listed by
     * getSensitiveProperties() are replaced with '[REDACTED]'.
     *
     * @return array<string, mixed>
     */
    private function buildRedactedPayload(object $command): array
    {
        $payload = get_object_vars($command);

        if ($command instanceof SensitiveDataInterface) {
            foreach ($command->getSensitiveProperties() as $field) {
                if (array_key_exists($field, $payload)) {
                    $payload[$field] = '[REDACTED]';
                }
            }
        }

        return $payload;
    }
}
