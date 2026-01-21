<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PruneIdempotencyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:prune-idempotency {--days=30 : Number of days to retain data} {--chunk=1000 : Deletion batch size}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old idempotency records to maintain database hygiene (Nibbling Strategy)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $chunkSize = (int) $this->option('chunk');
        $cutoff = Carbon::now()->subDays($days);

        $totalDeleted = 0;
        $startTime = microtime(true);

        try {
            do {
                // Phase 1: Identification (Select IDs)
                // Use limit at Select level (Universal compatibility)
                $candidates = DB::table('processed_commands')
                    ->where('status', 'COMPLETED')
                    ->where('created_at', '<', $cutoff)
                    ->limit($chunkSize)
                    ->pluck('idempotency_key')
                    ->toArray();

                $count = count($candidates);

                if ($count === 0) {
                    break;
                }

                // Phase 2: Surgical Elimination (Delete by ID)
                // Avoids Gap Locks and works on SQLite
                $affected = DB::table('processed_commands')
                    ->whereIn('idempotency_key', $candidates)
                    ->delete();

                $totalDeleted += $affected;

                if ($affected > 0) {
                    // Throttling to protect DB CPU
                    usleep(50000); 
                }

            } while ($count >= $chunkSize);

            $durationMs = (microtime(true) - $startTime) * 1000;

            // Phase 3: Observability (Structured JSON Log)
            $this->output->writeln(json_encode([
                "event" => "auth.idempotency.pruned",
                "rows_deleted" => $totalDeleted,
                "duration_ms" => round($durationMs, 2),
                "strategy" => "chunked_select_delete",
                "next_run_suggestion" => "now"
            ]));

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            // Error Handling: Log Critical failure
            $this->error(json_encode([
                "event" => "auth.idempotency.prune_failed",
                "error" => $e->getMessage(),
                "trace" => $e->getTraceAsString()
            ]));
            
            return Command::FAILURE;
        }
    }
}
