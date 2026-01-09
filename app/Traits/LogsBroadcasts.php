<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Log;

/**
 * LogsBroadcasts - Observability for Broadcast Events
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OBSERVABILITY LAYER:                                                   ║
 * ║    - Logs broadcast event emissions                                     ║
 * ║    - Enables debugging and monitoring                                   ║
 * ║    - Configurable log channel                                           ║
 * ║    - Fault-tolerant: never breaks the broadcast                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
trait LogsBroadcasts
{
    /**
     * Log channel for broadcast events.
     */
    private const BROADCAST_LOG_CHANNEL = 'broadcasting';

    /**
     * Log the broadcast event emission.
     *
     * This method is fault-tolerant - it will never throw an exception
     * that could interrupt the broadcast flow.
     *
     * @param  string  $eventName  The event name (e.g., 'order.paid')
     * @param  array<string, mixed>  $context  Additional context data
     */
    protected function logBroadcast(string $eventName, array $context = []): void
    {
        try {
            $channel = $this->resolveLogChannel();

            Log::channel($channel)->info("Broadcasting: {$eventName}", array_merge([
                'event' => $eventName,
                'class' => static::class,
                'timestamp' => now()->toIso8601String(),
            ], $context));
        } catch (\Throwable) {
            // Silent fail for logging - never interrupt the broadcast
            // In production, you might want to send this to a fallback logger
        }
    }

    /**
     * Log broadcast failure.
     *
     * @param  string  $eventName  The event name
     * @param  \Throwable  $exception  The exception that occurred
     * @param  array<string, mixed>  $context  Additional context
     */
    protected function logBroadcastFailure(string $eventName, \Throwable $exception, array $context = []): void
    {
        try {
            $channel = $this->resolveLogChannel();

            Log::channel($channel)->error("Broadcast failed: {$eventName}", array_merge([
                'event' => $eventName,
                'class' => static::class,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ], $context));
        } catch (\Throwable) {
            // Silent fail - logging should never break the application
        }
    }

    /**
     * Resolve the appropriate log channel.
     */
    private function resolveLogChannel(): string
    {
        // Check if broadcasting channel is configured
        if (config('logging.channels.'.self::BROADCAST_LOG_CHANNEL)) {
            return self::BROADCAST_LOG_CHANNEL;
        }

        // Fallback to default channel
        return config('logging.default', 'stack');
    }
}
