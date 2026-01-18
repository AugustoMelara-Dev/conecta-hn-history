<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Persistence;

use App\Domain\Common\Contracts\DomainEventInterface;
use App\Domain\Common\Contracts\OutboxRepositoryInterface;
use App\Infrastructure\Common\Models\OutboxMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * EloquentOutboxRepository - Transactional Event Storage Implementation
 *
 * UPDATED: Now implements Domain interface with append() method.
 * The store() method is kept as an alias for backward compatibility.
 */
final class EloquentOutboxRepository implements OutboxRepositoryInterface
{
    /**
     * Append a domain event to the outbox.
     * Primary method as per Domain interface.
     */
    public function append(DomainEventInterface $event): void
    {
        $this->persistEvent($event);
    }

    /**
     * Legacy alias for backward compatibility.
     * @deprecated Use append() instead.
     */
    public function store(DomainEventInterface $event): void
    {
        $this->persistEvent($event);
    }

    /**
     * Internal persistence logic.
     */
    private function persistEvent(DomainEventInterface $event): void
    {
        $payloadArray = $event->toPayload();
        $jsonPayload = json_encode($payloadArray, JSON_THROW_ON_ERROR);

        // PRIVACY SHIELD: Encrypt at Rest
        $encryptedPayload = Crypt::encryptString($jsonPayload);

        OutboxMessage::create([
            'event_type' => get_class($event),
            'payload' => $encryptedPayload,
            'occurred_at' => $event->occurredOn(),
            'processed' => false,
        ]);
    }

    public function getPendingMessages(int $limit = 50): array
    {
        // 1. Fetch raw candidates
        $messages = OutboxMessage::where('processed', false)
            ->whereNull('processed_at') // Double check for safety
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $events = [];

        foreach ($messages as $message) {
            try {
                // 2. SAFETY BLOCK: Decrypt & Decode
                $decryptedJson = Crypt::decryptString($message->payload);
                $payload = json_decode($decryptedJson, true, 512, JSON_THROW_ON_ERROR);
            
            } catch (DecryptException | JsonException $e) {
                // 3. PROTOCOL OF NEUTRALIZATION (The Dead Letter Rule)
                Log::critical("Corrupt Outbox Message ID {$message->id} neutralized. Reason: " . $e->getMessage());

                // "Radioactive" - Kill it immediately so it doesn't block the queue
                // We assume there's a JSON column 'error' or we just mark processed_at.
                // If schema doesn't have 'error', we'll just mark processed.
                $message->forceFill([
                    'processed' => true,
                    'processed_at' => now(),
                    // 'error' => substr($e->getMessage(), 0, 255) // Valid only if column exists
                ])->save();

                // 4. Skip processing, let the worker move on
                continue;
            }

            if (!class_exists($message->event_type)) {
                // Also neutralize messages with missing classes
                Log::error("Unknown Event Class {$message->event_type} for Message ID {$message->id}. Neutralizing.");
                $message->update(['processed' => true, 'processed_at' => now()]);
                continue;
            }

            // 5. Reconstruct Domain Event
            if (method_exists($message->event_type, 'fromPayload')) {
                $events[] = $message->event_type::fromPayload($payload);
            } else {
                $events[] = new $message->event_type(...$payload);
            }
        }

        return $events;
    }
    
    public function markAsProcessed(int $messageId): void
    {
        OutboxMessage::where('id', $messageId)->update(['processed' => true, 'processed_at' => now()]);
    }
}
