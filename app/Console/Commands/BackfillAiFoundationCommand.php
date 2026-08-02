<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ai\Conversation\AiFoundationBackfill;
use Illuminate\Console\Command;

final class BackfillAiFoundationCommand extends Command
{
    protected $signature = 'dovra:ai-foundation-backfill
        {--limit=500 : Maximum number of executions/conversations to inspect}
        {--phase=backfill : expand, backfill, validate, or contract}
        {--cursor= : Opaque execution cursor returned by the previous batch}
        {--conversation-cursor= : Opaque conversation cursor returned by the previous batch}
        {--checkpoint= : JSON checkpoint file to read and update atomically}
        {--dry-run : Validate the batch without mutating data}
        {--json : Emit a machine-readable report}';

    protected $description = 'Backfill canonical AI foundation lineage, retention defaults, and conversation links.';

    public function handle(AiFoundationBackfill $backfill): int
    {
        $phase = (string) $this->option('phase');
        $cursor = $this->option('cursor');
        $conversationCursor = $this->option('conversation-cursor');
        $checkpoint = $this->option('checkpoint');

        if (is_string($checkpoint) && trim($checkpoint) !== '' && is_file($checkpoint)) {
            $contents = file_get_contents($checkpoint);
            $decoded = is_string($contents) ? json_decode($contents, true) : null;
            if (is_array($decoded)) {
                $cursor ??= is_string($decoded['next_cursor'] ?? null) ? $decoded['next_cursor'] : null;
                $conversationCursor ??= is_string($decoded['conversation_next_cursor'] ?? null)
                    ? $decoded['conversation_next_cursor']
                    : null;
            }
        }

        $report = $backfill->run(
            limit: max(1, (int) $this->option('limit')),
            cursor: is_string($cursor) ? $cursor : null,
            phase: $phase,
            dryRun: (bool) $this->option('dry-run'),
            conversationCursor: is_string($conversationCursor) ? $conversationCursor : null,
        )->toArray();

        if (is_string($checkpoint) && trim($checkpoint) !== '') {
            $directory = dirname($checkpoint);
            if (! is_dir($directory)) {
                $this->error('Checkpoint directory does not exist: '.$directory);

                return self::FAILURE;
            }
            $temporary = $checkpoint.'.'.bin2hex(random_bytes(6)).'.tmp';
            file_put_contents($temporary, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if (! rename($temporary, $checkpoint)) {
                @unlink($temporary);
                $this->error('Unable to atomically update checkpoint: '.$checkpoint);

                return self::FAILURE;
            }
        }

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
