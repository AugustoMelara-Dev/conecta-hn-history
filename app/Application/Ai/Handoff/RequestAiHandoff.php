<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

final readonly class RequestAiHandoff
{
    public function __construct(
        public string $tenantId,
        public string $conversationId,
        public string $triggeringMessageId,
        public string $agentVersionId,
        public string $triggerType,
        public string $priority,
        public string $queueKey,
        public string $contextSummary,
    ) {}
}
