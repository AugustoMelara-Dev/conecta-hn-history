<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Persistence;

use App\Application\Common\Interfaces\OutboxRepositoryInterface;
use App\Domain\Common\DomainEvent;
use App\Infrastructure\Common\Models\OutboxMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use JsonException;

final class EloquentOutboxRepository implements OutboxRepositoryInterface
{
    public function store(DomainEvent $event): void
    {
        $payloadArray = $event->toPayload();
        $jsonPayload = json_encode($payloadArray, JSON_THROW_ON_ERROR);

        // PRIVACY SHIELD: Encrypt at Rest
        $encryptedPayload = Crypt::encryptString($jsonPayload);

        OutboxMessage::create([
            'event_type' => get_class($event),
            'payload' => $encryptedPayload,
            'occurred_at' => $event->occurredAt(),
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
