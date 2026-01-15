<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Common\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Infrastructure\Common\LaravelTransactionManager;

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
    protected $description = 'Safely re-queue failed or published events for processing';

    public function handle(): int
    {
        $id = $this->option('id');
        $type = $this->option('type');
        $hours = $this->option('hours');

        // Safety Valve: Prevent accidental full replay
        if (!$id && !$type && !$hours) {
            $this->warn('WARNING: You are about to replay ALL historical events.');
            if (!$this->confirm('Are you absolutely sure you want to replay ALL messages? This may cause massive side effects.')) {
                $this->info('Operation Aborted.');
                return Command::FAILURE;
            }
        }

        $query = OutboxMessage::query();

        // Apply Filters
        $query->when($id, fn($q) => $q->where('id', $id));
        $query->when($type, fn($q) => $q->where('type', 'LIKE', "%{$type}%"));
        $query->when($hours, fn($q) => $q->where('created_at', '>=', now()->subHours((int)$hours)));

        $count = $query->count();

        if ($count === 0) {
            $this->info('No messages found matching criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found {$count} messages to replay.");

        // Transactional Update
        DB::transaction(function () use ($query) {
            // We reset them to PENDING so the standard Worker picks them up.
            // We verify the Worker handles Idempotency or the Receivers do.
            $query->update([
                'status' => 'PENDING',
                'error' => null, // Clear previous error logs
                'updated_at' => now(),
            ]);
        });

        $this->success("Successfully queued {$count} events for replay.");
        
        return Command::SUCCESS;
    }
}
