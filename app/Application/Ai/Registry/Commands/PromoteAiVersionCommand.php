<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry\Commands;

final readonly class PromoteAiVersionCommand
{
    public function __construct(
        public string $actorId,
        public ?string $tenantId,
        public string $versionType,
        public string $versionId,
        public string $targetStatus,
        public ?string $releaseDecisionId,
        public string $reason,
    ) {}
}
