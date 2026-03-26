<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class CustomerProfileTableFilterOptionsReader
{
    public function __construct(
        private readonly TenantCustomerProfilePresentationReader $presentationReader,
    ) {}

    /**
     * @return array<string, string>
     */
    public function lifecycleStageOptions(): array
    {
        return $this->presentationReader->lifecycleStageOptions();
    }

    /**
     * @return array<string, string>
     */
    public function segmentOptions(): array
    {
        return $this->presentationReader->manualSegmentOptions();
    }

    /**
     * @return array<string, string>
     */
    public function preferredChannelOptions(): array
    {
        return $this->presentationReader->preferredChannelOptions();
    }

    /**
     * @return array<string, string>
     */
    public function loyaltyTierOptions(): array
    {
        return $this->presentationReader->loyaltyTierOptions();
    }
}
