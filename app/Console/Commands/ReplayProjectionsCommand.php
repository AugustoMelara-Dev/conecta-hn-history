<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Common\Projections\ProjectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * ReplayProjectionsCommand - The Reset Button
 * 
 * USE WITH CAUTION. Truncates read tables!
 */
class ReplayProjectionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projections:replay 
                            {projector=all : The specific projector class to replay, or "all"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuilds read models (projections) from the Event Store history.';

    /**
     * Execute the console command.
     */
    public function handle(ProjectionManager $manager): int
    {
        $projectorInput = $this->argument('projector');
        $isProduction = App::isProduction();

        // 1. SAFETY CATCH (Production Guard)
        if ($isProduction) {
            $this->alert('⚠️  WARNING: YOU ARE ABOUT TO TRUNCATE READ TABLES IN PRODUCTION ⚠️');
            
            if (! $this->confirm("Are you sure you want to rebuild [{$projectorInput}]? This implies downtime for read models.")) {
                $this->info('Operation aborted by user.');
                return 1;
            }
        }

        $this->info("Starting Replay for: [{$projectorInput}]...");
        $startTime = microtime(true);

        try {
            // Callback for visual feedback
            $onProgress = function (int $count) {
                 $this->output->write("\rProcessed Events: {$count}   ");
            };

            if ($projectorInput === 'all') {
                $manager->replayAll($onProgress);
            } else {
                // Allow partial class names for verify
                // Ideally user provides full class, but we assume exact match for now as per "Strict" requirements
                $manager->replayProjector($projectorInput, $onProgress);
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("✅ Replay Complete. Duration: {$duration}s");
            
            return 0;

        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("❌ Replay Failed: " . $e->getMessage());
            return 1;
        }
    }
}
