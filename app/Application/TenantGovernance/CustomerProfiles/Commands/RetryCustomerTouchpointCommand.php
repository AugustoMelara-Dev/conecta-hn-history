<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\CustomerProfiles\Commands;

final readonly class RetryCustomerTouchpointCommand
{
    public function __construct(
        public string $tenantId,
        public int $touchpointId,
        public ?string $actorUserId,
    ) {}
}
