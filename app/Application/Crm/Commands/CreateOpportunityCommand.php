<?php

declare(strict_types=1);

namespace App\Application\Crm\Commands;

final readonly class CreateOpportunityCommand
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $idempotencyKey,
        public string $title,
        public int $valueMinor = 0,
        public ?string $currency = null,
        public ?int $pipelineId = null,
        public ?int $stageId = null,
        public ?int $customerProfileId = null,
        public ?int $leadId = null,
        public ?string $ownerUserId = null,
        public ?string $locationId = null,
        public ?string $source = null,
        public ?string $expectedCloseDate = null,
        public ?string $lostReason = null,
        public ?string $notes = null,
    ) {}
}
