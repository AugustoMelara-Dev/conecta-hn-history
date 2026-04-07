<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class SendCustomerProfileEmailFollowUpCommand
{
    public function __construct(
        public string $tenantId,
        public int $profileId,
        public string $subject,
        public string $body,
        public string $automationKey,
        public ?string $actorUserId,
    ) {}
}
