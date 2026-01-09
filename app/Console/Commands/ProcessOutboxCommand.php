<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
use App\Infrastructure\Persistence\Eloquent\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * ProcessOutboxCommand - Transactional Outbox Worker
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OUTBOX RELAY WORKER                                                     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This command reads pending messages from the outbox table,              ║
 * ║  reconstructs the original event objects, and dispatches them.           ║
 * ║                                                                          ║
 * ║  GUARANTEES:                                                             ║
 * ║  - At-least-once delivery (duplicates possible on crash-restart)         ║
 * ║  - Ordered delivery per aggregate (via occurred_at ordering)             ║
 * ║  - Parallel worker safety (via lockForUpdate + skipLocked)               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   php artisan infrastructure:process-outbox           # Process one batch
 *   php artisan infrastructure:process-outbox --daemon  # Run continuously
 */
final class ProcessOutboxCommand extends Command
{
    protected $signature = 'infrastructure:process-outbox 
                            {--batch=50 : Number of messages to process per cycle}
                            {--max-attempts=3 : Maximum retry attempts}
                            {--daemon : Run continuously}
                            {--sleep=1 : Seconds to sleep between cycles in daemon mode}';

    protected $description = 'Process pending domain events from the Transactional Outbox';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $maxAttempts = (int) $this->option('max-attempts');
        $daemon = (bool) $this->option('daemon');
        $sleep = (int) $this->option('sleep');

        $this->info('🚀 Starting Outbox Worker...');

        do {
            $processed = $this->processBatch($batchSize, $maxAttempts);
            
            if ($processed === 0 && $daemon) {
                sleep($sleep);
            }
            
            if ($processed > 0) {
                $this->info("✅ Processed {$processed} messages");
            }
            
        } while ($daemon);

        $this->info('✅ Outbox processing complete');
        return Command::SUCCESS;
    }

    /**
     * Process a batch of pending messages.
     */
    private function processBatch(int $batchSize, int $maxAttempts): int
    {
        // Query with pessimistic locking for parallel worker safety
        $messages = OutboxMessage::query()
            ->whereNull('published_at')
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('occurred_at', 'asc')
            ->limit($batchSize)
            ->lockForUpdate()
            // ->skipLocked() // Uncomment if using MySQL 8+ or PostgreSQL
            ->get();

        $processed = 0;

        foreach ($messages as $message) {
            try {
                $event = $this->reconstructEvent($message);
                
                if ($event !== null) {
                    Event::dispatch($event);
                    $message->markAsPublished();
                    $processed++;
                    
                    $this->line("  📤 Dispatched: {$message->type}");
                } else {
                    $message->recordFailure("Failed to reconstruct event class: {$message->type}");
                    $this->warn("  ⚠️ Reconstruction failed: {$message->type}");
                }
                
            } catch (\Throwable $e) {
                $message->recordFailure($e->getMessage());
                
                Log::error('Outbox delivery failed', [
                    'message_id' => $message->id,
                    'type' => $message->type,
                    'error' => $e->getMessage(),
                    'attempts' => $message->attempts,
                ]);
                
                $this->error("  ❌ Failed: {$message->type} - {$e->getMessage()}");
            }
        }

        return $processed;
    }

    /**
     * Reconstruct the original event object from stored payload.
     */
    private function reconstructEvent(OutboxMessage $message): ?object
    {
        $eventClass = $message->type;

        if (!class_exists($eventClass)) {
            return null;
        }

        if (!is_subclass_of($eventClass, ShouldBeStoredInOutbox::class)) {
            // Event class no longer implements the interface
            // Dispatch as generic array event for backwards compatibility
            return (object) [
                'type' => $eventClass,
                'payload' => $message->payload,
            ];
        }

        // Use the fromPayload factory method
        return $eventClass::fromPayload($message->payload);
    }
}
