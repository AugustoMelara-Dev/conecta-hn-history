<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplayOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:replay 
                            {--id= : Replay a specific message ID} 
                            {--type= : Replay messages of a specific Event Class (e.g. UserRegistered)}
                            {--hours= : Replay messages created in the last N hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely re-queue only failed or quarantined events for processing';

    public function handle(): int
    {
        $id = $this->option('id');
        $type = $this->option('type');
        $hours = $this->option('hours');

        // Safety Valve: Prevent accidental full replay
        if (! $id && ! $type && ! $hours) {
            $this->warn('WARNING: You are about to replay ALL historical events.');
            if (! $this->confirm('Are you absolutely sure you want to replay ALL messages? This may cause massive side effects.')) {
                $this->info('Operation Aborted.');

                return Command::FAILURE;
            }
        }

        $query = OutboxMessage::query();

        // Apply Filters
        $query->when($id, fn ($q) => $q->where('id', $id));
        $query->when($type, fn ($q) => $q->where('type', 'LIKE', "%{$type}%"));
        $query->when($hours, fn ($q) => $q->where('created_at', '>=', now()->subHours((int) $hours)));

        $eligibleStatuses = [
            OutboxMessage::STATUS_FAILED,
            OutboxMessage::STATUS_QUARANTINED,
        ];

        $count = DB::transaction(function () use ($query, $eligibleStatuses): int {
            $ids = (clone $query)
                ->whereIn('status', $eligibleStatuses)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            // The status predicate is a compare-and-set guard. Even on a database
            // without effective row locks, an active worker state is never reset.
            return OutboxMessage::query()
                ->whereIn('id', $ids)
                ->whereIn('status', $eligibleStatuses)
                ->update([
                    'status' => OutboxMessage::STATUS_PENDING,
                    'attempts' => 0,
                    'recovery_count' => 0,
                    'locked_at' => null,
                    'worker_id' => null,
                    'batch_correlation_id' => null,
                    'failed_at' => null,
                    'quarantined_at' => null,
                    'published_at' => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        });

        if ($count === 0) {
            $this->info('No failed or quarantined messages found matching criteria.');

            return Command::SUCCESS;
        }

        $this->info("Successfully queued {$count} events for replay.");

        return Command::SUCCESS;
    }
}
