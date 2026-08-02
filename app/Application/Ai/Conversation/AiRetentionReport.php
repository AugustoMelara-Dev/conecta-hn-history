<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

final readonly class AiRetentionReport
{
    /**
     * @param  list<string>  $failures
     */
    public function __construct(
        public int $conversationsPruned,
        public int $messagesRedacted,
        public int $evidenceRedacted,
        public int $providerPayloadsPruned,
        public int $legalHoldsSkipped,
        public array $failures,
    ) {}

    /**
     * @return array{
     *     conversations_pruned:int,
     *     messages_redacted:int,
     *     evidence_redacted:int,
     *     provider_payloads_pruned:int,
     *     legal_holds_skipped:int,
     *     failures:list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'conversations_pruned' => $this->conversationsPruned,
            'messages_redacted' => $this->messagesRedacted,
            'evidence_redacted' => $this->evidenceRedacted,
            'provider_payloads_pruned' => $this->providerPayloadsPruned,
            'legal_holds_skipped' => $this->legalHoldsSkipped,
            'failures' => $this->failures,
        ];
    }

    public function hasBlockingFailures(): bool
    {
        return $this->failures !== [];
    }
}
