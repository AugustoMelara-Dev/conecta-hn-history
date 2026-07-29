<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ai\Registry\AiRegistryBackfill;
use Illuminate\Console\Command;

final class BackfillAiRegistryCommand extends Command
{
    protected $signature = 'dovra:ai-registry-backfill {--json : Emit a machine-readable report}';

    protected $description = 'Bootstrap versioned AI provider, model, agent, and prompt registries.';

    public function handle(AiRegistryBackfill $backfill): int
    {
        $report = $backfill->run()->toArray();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($report as $name => $count) {
                $this->line($name.': '.$count);
            }
        }

        return self::SUCCESS;
    }
}
