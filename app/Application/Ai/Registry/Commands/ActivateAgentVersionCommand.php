<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry\Commands;

final readonly class ActivateAgentVersionCommand
{
    public function __construct(
        public string $actorId,
        public ?string $tenantId,
        public string $scopeType,
        public string $scopeKey,
        public string $agentVersionId,
        public string $releaseDecisionId,
        public int $rolloutPercentage,
        public string $reason,
        public ?string $expectedActivationId = null,
        public ?int $expectedLockVersion = null,
    ) {}
}
