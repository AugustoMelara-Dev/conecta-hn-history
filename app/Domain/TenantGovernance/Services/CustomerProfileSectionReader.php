<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileSectionReader
{
    public function __construct(
        private readonly CustomerProfileSummaryStateReader $summaryReader,
        private readonly CustomerProfileLoyaltyStateReader $loyaltyReader,
    ) {}

    /**
     * @return array<string, string|int|float|null>
     */
    public function profileSummary(CustomerProfile $profile): array
    {
        return $this->summaryReader->read($profile);
    }

    /**
     * @return array<string, string|int|float|null>
     */
    public function loyaltySummary(CustomerProfile $profile): array
    {
        return $this->loyaltyReader->read($profile);
    }
}
