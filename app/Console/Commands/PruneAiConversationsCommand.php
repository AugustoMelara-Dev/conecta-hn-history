<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ai\Conversation\AiConversationRetention;
use Illuminate\Console\Command;

final class PruneAiConversationsCommand extends Command
{
    protected $signature = 'dovra:ai-conversations-prune
        {--limit=500 : Maximum expired conversations to process}
        {--json : Emit a machine-readable report}';

    protected $description = 'Redact expired AI conversation payloads while preserving ledger and audit references.';

    public function __construct(
        private readonly AiConversationRetention $retention,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->retention->prune((int) $this->option('limit'));
        $hasBlockingFailures = $report->hasBlockingFailures();
        $payload = [
            'status' => $hasBlockingFailures ? 'failed' : 'ok',
            ...$report->toArray(),
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
        } else {
            $this->info(sprintf(
                'AI conversations pruned: %d conversations; %d messages; %d evidence rows; %d provider payloads.',
                $report->conversationsPruned,
                $report->messagesRedacted,
                $report->evidenceRedacted,
                $report->providerPayloadsPruned,
            ));
        }

        return $hasBlockingFailures ? self::FAILURE : self::SUCCESS;
    }
}
