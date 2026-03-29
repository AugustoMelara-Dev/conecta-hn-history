<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;

final class CustomerProfileLoyaltyStateReader
{
    /**
     * @return array<string, string|int|float|null>
     */
    public function read(CustomerProfile $profile): array
    {
        return [
            'acquisition_channel' => $profile->acquisition_channel_label,
            'loyalty_tier' => $profile->loyalty_tier_label,
            'loyalty_points_balance' => (int) $profile->loyalty_points_balance,
            'lifetime_points_earned' => (int) $profile->lifetime_points_earned,
            'initial_ltv' => (float) $profile->initial_ltv,
            'redeemable_rewards_count' => (int) $profile->redeemable_rewards_count,
            'next_reward_name' => $profile->next_reward_name,
            'points_to_next_reward' => (int) $profile->points_to_next_reward,
            'next_reward_copy' => $profile->next_reward_copy,
        ];
    }
}
