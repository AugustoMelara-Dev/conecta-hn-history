<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Common\EventStore\ProjectorInterface;
use App\Infrastructure\Identity\Projections\UserProjector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReplayEventsCommand extends Command
{
    protected $signature = 'event-sourcing:replay {--projector= : Specific projector class to run}';

    protected $description = 'Rehydrate Read Models from the Event Stream (Source of Truth)';

    public function handle(): int
    {
        $this->info('Starting Titanium Replay Protocol...');

        // 1. Resolve Projectors
        $projectors = $this->resolveProjectors();

        if (empty($projectors)) {
            $this->error('No projectors found.');

            return Command::FAILURE;
        }

        // 2. Reset Read Models (Panic Button)
        if ($this->confirm('WARNING: This will TRUNCATE the Read Models for the selected projectors. Are you sure?', true)) {
            foreach ($projectors as $projector) {
                $this->info('Resetting '.get_class($projector).'...');
                $projector->reset();
            }
        } else {
            return Command::SUCCESS;
        }

        $this->info('Streaming Stored Events...');

        $count = 0;
        $errors = 0;

        // 3. Lazy Cursor for Infinite Stream (Memory Constant)
        $cursor = DB::table('stored_events')
            ->orderBy('id')
            ->cursor();

        foreach ($cursor as $record) {
            try {
                $eventType = $record->event_type;
                $payload = json_decode($record->payload, true);

                // 4. Deserialization (Hydration)
                // We assume strict naming convention or internal mapping.
                // For now, we instantiate directly if class exists.
                if (! class_exists($eventType)) {
                    // Skip unknown events (Legacy protection)
                    continue;
                }

                // Hydrate Event
                // Assuming static fromPayload method typically used in our DomainEvents
                if (method_exists($eventType, 'fromPayload')) {
                    $event = $eventType::fromPayload($payload);
                } else {
                    // Fallback to constructor unwrapping (risky if signature mismatch, but standard for DTOs)
                    // Reflexion could be used here. For strictness, fromPayload is best.
                    continue;
                }

                // 5. Projection
                foreach ($projectors as $projector) {
                    if ($projector->supports($event)) {
                        $projector->project($event);
                    }
                }

                $count++;
                if ($count % 100 === 0) {
                    $this->output->write('.');
                }

            } catch (\Throwable $e) {
                $errors++;
                Log::warning("Replay Error at ID {$record->id}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Replay Complete. Processed {$count} events. Errors: {$errors}.");

        return Command::SUCCESS;
    }

    /**
     * @return ProjectorInterface[]
     */
    private function resolveProjectors(): array
    {
        $requested = $this->option('projector');

        // Registry of all projectors
        // In a real app, tagged in ServiceContainer.
        $all = [
            app(UserProjector::class),
        ];

        if ($requested) {
            return array_filter($all, fn ($p) => get_class($p) === $requested || class_basename($p) === $requested);
        }

        return $all;
    }
}
