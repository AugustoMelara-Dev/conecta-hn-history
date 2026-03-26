<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileTableLifecycleReader
{
    public function __construct(
        private readonly TenantCustomerProfilePresentationReader $presentationReader,
    ) {}

    public function segmentLabel(CustomerProfile $profile): string
    {
        return $profile->segment_display_label;
    }

    public function segmentCopy(CustomerProfile $profile): string
    {
        return $this->presentationReader->nextTouchCopy($profile);
    }

    public function segmentTone(CustomerProfile $profile): string
    {
        return match ($profile->segment_display) {
            'vip' => 'success',
            'repeat' => 'info',
            'reactivation' => 'warning',
            'prospect' => 'gray',
            default => 'primary',
        };
    }

    public function lifecycleLabel(CustomerProfile $profile): string
    {
        return $profile->lifecycle_stage_label;
    }

    public function lifecycleTone(CustomerProfile $profile): string
    {
        return match ($profile->lifecycle_stage) {
            'loyal' => 'success',
            'active' => 'info',
            'dormant' => 'warning',
            default => 'gray',
        };
    }

    public function loyaltyLabel(CustomerProfile $profile): string
    {
        return $profile->loyalty_tier_label;
    }

    public function loyaltyCopy(CustomerProfile $profile): string
    {
        return $this->presentationReader->loyaltyTierCopy($profile);
    }

    public function loyaltyTone(CustomerProfile $profile): string
    {
        return match ($profile->loyalty_tier) {
            'vip' => 'success',
            'gold' => 'warning',
            'silver' => 'info',
            default => 'gray',
        };
    }

    public function loyaltyPointsCopy(CustomerProfile $profile): string
    {
        return $profile->next_reward_copy;
    }

    public function reactivationLabel(CustomerProfile $profile): string
    {
        return $profile->reactivation_label;
    }

    public function reactivationTone(CustomerProfile $profile): string
    {
        return match (true) {
            $profile->reactivation_score >= 85 => 'danger',
            $profile->reactivation_score >= 65 => 'warning',
            $profile->reactivation_score >= 45 => 'info',
            default => 'gray',
        };
    }
}
