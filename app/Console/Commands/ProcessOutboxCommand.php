<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Common\Outbox\MessagePublisherInterface;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProcessOutboxCommand - Compatibility processor for quick ops/testing.
 *
 * Supports single-pass execution for tests and bounded batches for
 * operational runs, using pessimistic row locking with SKIP LOCKED.
 */
class ProcessOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:process
                            {--once : Process one cycle and exit}
                            {--limit=100 : Max pending messages per cycle}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending outbox messages with optional single-pass mode.';

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
    public function handle(): int
    {
        $this->info('Starting Outbox Processor...');
        $once = (bool) $this->option('once');
        $limit = max(1, (int) $this->option('limit'));
        $totalProcessed = 0;

        while (true) {
            $messages = DB::transaction(function () use ($limit) {
                $query = DB::table('outbox_messages')
                    ->where('status', 'PENDING')
                    ->orderBy('occurred_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->limit($limit);

                try {
                    return $query->lock('FOR UPDATE SKIP LOCKED')->get();
                } catch (QueryException $exception) {
                    // MariaDB variants may not support SKIP LOCKED; fallback keeps compatibility.
                    return DB::table('outbox_messages')
                        ->where('status', 'PENDING')
                        ->orderBy('occurred_at', 'asc')
                        ->orderBy('id', 'asc')
                        ->limit($limit)
                        ->lockForUpdate()
                        ->get();
                }
            }, 3);

            if ($messages->isEmpty()) {
                if ($once) {
                    break;
                }
                sleep(1);

                continue;
            }

            $count = 0;
            foreach ($messages as $message) {
                try {
                    $this->publisher->publish($message->type, $message->payload);
                    DB::table('outbox_messages')
                        ->where('id', $message->id)
                        ->where('status', 'PENDING')
                        ->update([
                            'published_at' => now(),
                            'status' => 'COMPLETED',
                            'updated_at' => now(),
                        ]);

                    $count++;
                } catch (Throwable $e) {
                    Log::error("Failed to publish message ID {$message->id}: ".$e->getMessage());
                }
            }

            $totalProcessed += $count;
            $this->info("Processed batch of {$count} messages.");

            if ($once) {
                break;
            }
        }

        $this->info("Total processed: {$totalProcessed}");

        return self::SUCCESS;
    }
}
