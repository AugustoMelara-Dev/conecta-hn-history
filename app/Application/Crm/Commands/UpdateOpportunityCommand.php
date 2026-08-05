<?php

declare(strict_types=1);

namespace App\Application\Crm\Commands;

final readonly class UpdateOpportunityCommand
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public int $dealId,
        public ?string $title = null,
        public ?int $valueMinor = null,
        public ?string $currency = null,
        public ?int $customerProfileId = null,
        public bool $customerProfileSpecified = false,
        public ?int $leadId = null,
        public bool $leadSpecified = false,
        public ?string $ownerUserId = null,
        public bool $ownerSpecified = false,
        public ?string $locationId = null,
        public bool $locationSpecified = false,
        public ?string $source = null,
        public bool $sourceSpecified = false,
        public ?string $expectedCloseDate = null,
        public bool $expectedCloseDateSpecified = false,
        public ?string $lostReason = null,
        public bool $lostReasonSpecified = false,
        public ?string $notes = null,
        public bool $notesSpecified = false,
    ) {}
}
