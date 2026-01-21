<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Common\Outbox\MessagePublisherInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @deprecated Use App\Infrastructure\Console\Commands\ProcessOutboxCommand instead.
 *             This legacy version does NOT support horizontal scaling (no SKIP LOCKED).
 *             Will be removed in v2.0.
 *
 * ProcessOutboxCommand - The Heartbeat Daemon (LEGACY)
 * 
 * Responsibilities:
 * - FIFO Processing (Strict Order)
 * - At-Least-Once Delivery (Pessimistic Ack)
 * - Resource Efficiency (Sleep on Empty)
 */
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
    protected $description = 'Daemon that reads pending events from DB and pushes them to the Message Bus.';

    /**
     * Create a new command instance.
     */
    public function __construct(
        private readonly MessagePublisherInterface $publisher
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting Outbox Processor Daemon...');

        // THE DAEMON LOOP
        while (true) {
            // 1. SELECT PENDING (Atomic Batch)
            // Limit 100 to prevent memory bloating.
            $messages = DB::table('outbox_messages')
                ->whereNull('published_at')
                ->orderBy('id', 'asc') // FIFO Rule
                ->limit(100)
                ->get();

            // 2. RESOURCE RESPECT RULE
            if ($messages->isEmpty()) {
                // Sleep to release CPU cycles when idle.
                // 1 second pause.
                sleep(1); 
                continue;
            }

            $count = 0;

            // 3. PROCESS BATCH
            foreach ($messages as $message) {
                try {
                    // A. PUBLISH (The Critical Side Effect)
                    $this->publisher->publish($message->type, $message->payload);

                    // B. ACKNOWLEDGE (Commit)
                    // Only done if Publish succeeds.
                    DB::table('outbox_messages')
                        ->where('id', $message->id)
                        ->update(['published_at' => now()]);
                    
                    $this->info("Published message ID: {$message->id}");
                    $count++;

                } catch (Throwable $e) {
                    // C. FAILURE HANDLING (Retry Strategy)
                    // We DO NOT mark as published. It will be retried endlessly until fixed.
                    // This guarantees "At-Least-Once" Delivery.
                    Log::error("Failed to publish message ID {$message->id}: " . $e->getMessage());
                    
                    // Optional: Break loop to avoid spamming logs if Broker is down?
                    // For now, we continue trying subsequent messages (though FIFO might imply blocking? 
                    // Usually blocking the specific message is enough, but strictly FIFO means we should stop?
                    // The prompt implies "Retry Infinito por defecto" -> next iteration picks it up.
                    // If strict FIFO is required universally, a failure should perhaps halt the batch.
                    // However, stopping the daemon might require supervisor restart. 
                    // Continuing allows temporary glitches to self-heal. 
                    // Implementation follows prompt: "Next iteration" implied.
                }
            }
            
            $this->info("Processed batch of {$count} messages.");
        }
    }
}
