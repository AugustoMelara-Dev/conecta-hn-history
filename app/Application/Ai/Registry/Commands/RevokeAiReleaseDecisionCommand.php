<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry\Commands;

final readonly class RevokeAiReleaseDecisionCommand
{
    public function __construct(
        public string $actorId,
        public ?string $tenantId,
        public string $decisionId,
        public string $reason,
    ) {}
}
