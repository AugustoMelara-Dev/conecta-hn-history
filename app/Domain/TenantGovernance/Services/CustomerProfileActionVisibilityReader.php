<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileActionVisibilityReader
{
    public function canRedeemReward(CustomerProfile $profile): bool
    {
        return (int) $profile->redeemable_rewards_count > 0;
    }

    public function canEmailFollowUp(CustomerProfile $profile): bool
    {
        return filled($profile->email);
    }

    public function canWhatsappFollowUp(CustomerProfile $profile): bool
    {
        return filled($profile->phone);
    }

    public function canTriggerReactivation(CustomerProfile $profile): bool
    {
        return (int) $profile->reactivation_score >= 65 && filled($profile->email);
    }
}
