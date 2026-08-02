<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ai\Conversation\AiFoundationBackfill;
use Illuminate\Console\Command;

final class BackfillAiFoundationCommand extends Command
{
    protected $signature = 'dovra:ai-foundation-backfill
        {--limit=500 : Maximum number of executions/conversations to inspect}
        {--json : Emit a machine-readable report}';

    protected $description = 'Backfill canonical AI foundation lineage, retention defaults, and conversation links.';

    public function handle(AiFoundationBackfill $backfill): int
    {
        $report = $backfill->run(max(1, (int) $this->option('limit')))->toArray();

        if ((bool) $this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ));
        } else {
            foreach ($report as $name => $count) {
                $this->line($name.': '.$count);
            }
        }

        return self::SUCCESS;
    }
}
