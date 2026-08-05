<?php

declare(strict_types=1);

namespace App\Application\Crm\Commands;

final readonly class MoveOpportunityStageCommand
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public int $dealId,
        public int $stageId,
        public ?string $lostReason = null,
    ) {}
}
