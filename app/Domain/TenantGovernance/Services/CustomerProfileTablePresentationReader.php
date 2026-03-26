<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileTablePresentationReader
{
    public function __construct(
        private readonly CustomerProfileTableIdentityReader $identityReader,
        private readonly CustomerProfileTableLifecycleReader $lifecycleReader,
    ) {}

    public function customerIdentity(CustomerProfile $profile): string
    {
        return $this->identityReader->customerIdentity($profile);
    }

    public function segmentLabel(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->segmentLabel($profile);
    }

    public function segmentCopy(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->segmentCopy($profile);
    }

    public function segmentTone(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->segmentTone($profile);
    }

    public function lifecycleLabel(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->lifecycleLabel($profile);
    }

    public function lifecycleTone(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->lifecycleTone($profile);
    }

    public function loyaltyLabel(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->loyaltyLabel($profile);
    }

    public function loyaltyCopy(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->loyaltyCopy($profile);
    }

    public function loyaltyTone(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->loyaltyTone($profile);
    }

    public function ownerLabel(CustomerProfile $profile): string
    {
        return $this->identityReader->ownerLabel($profile);
    }

    public function locationLabel(CustomerProfile $profile): string
    {
        return $this->identityReader->locationLabel($profile);
    }

    public function loyaltyPointsCopy(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->loyaltyPointsCopy($profile);
    }

    public function reactivationLabel(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->reactivationLabel($profile);
    }

    public function reactivationTone(CustomerProfile $profile): string
    {
        return $this->lifecycleReader->reactivationTone($profile);
    }

    public function lastActivityCopy(CustomerProfile $profile): string
    {
        return $this->identityReader->lastActivityCopy($profile);
    }
}
