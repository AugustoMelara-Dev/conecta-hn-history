<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Outbox;

use Illuminate\Support\Facades\Log;

/**
 * LogMessagePublisher - Local/Debug Adapter
 *
 * Simulates message dispatch by writing to the application log.
 * Useful for development and as a fallback.
 */
final readonly class LogMessagePublisher implements MessagePublisherInterface
{
    public function publish(string $topic, string $payload): void
    {
        // Simulate Network Latency? No, keep it fast.

        // Critical: If logging fails (filesystem full), this should throw exception naturally,
        // preventing the Outbox Worker from marking the message as delivered.
        Log::info(" [OUTBOX DISPATCH] Topic: {$topic} | Payload: {$payload}");
    }
}
