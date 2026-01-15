<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Common\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending domain events from the transactional outbox';

    public function handle(): int
    {
        $this->info('Starting Outbox Processor...');

        // Process in chunks to manage memory
        OutboxMessage::where('status', 'PENDING')
            ->chunkById(100, function ($messages) {
                foreach ($messages as $message) {
                    try {
                        // 1. Reconstruct Event
                        // Assuming payload structure is compatible with standard serialization
                        // If complex objects are needed, a specialized Serializer service should be injected.
                        // Here we utilize a simple array-based reconstruction or assume the Payload IS the data.
                        
                        $eventClass = $message->type;
                        if (!class_exists($eventClass)) {
                            throw new \RuntimeException("Event class {$eventClass} not found.");
                        }

                        // Reconstitution Strategy:
                        // If the event has a static 'fromPayload' method (best practice), use it.
                        // Otherwise, attempt constructor injection (PHP 8 named arguments).
                         
                        $event = null;
                        if (method_exists($eventClass, 'fromPayload')) {
                            $event = $eventClass::fromPayload($message->payload);
                        } else {
                            // Fallback: Named Arguments Unpacking
                            // This assumes payload keys match constructor argument names exactly.
                             $event = new $eventClass(...$message->payload);
                        }

                        // 2. Dispatch to Real Bus
                        Event::dispatch($event);

                        // 3. Mark as Published
                        $message->update([
                            'status' => 'PUBLISHED',
                        ]);
                        
                        // We use touch to update updated_at effectively as processed_at
                        $message->touch();

                    } catch (Throwable $e) {
                        Log::error('Outbox Processing Failed', [
                            'message_id' => $message->id,
                            'error' => $e->getMessage(),
                        ]);

                        $message->update([
                            'status' => 'FAILED',
                        ]);
                    }
                }
            });

        $this->info('Outbox Batch Complete.');
        
        return Command::SUCCESS;
    }
}
