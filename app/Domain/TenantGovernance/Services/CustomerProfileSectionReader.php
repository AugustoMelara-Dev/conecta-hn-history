<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileSectionReader
{
    /**
     * @return array<string, string|int|float|null>
     */
    public function profileSummary(CustomerProfile $profile): array
    {
        return [
            'name' => $profile->name,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'orders_count' => (int) $profile->orders_count,
            'repeat_purchase_count' => (int) $profile->repeat_purchase_count,
            'appointments_count' => (int) $profile->appointments_count,
            'leads_count' => (int) $profile->leads_count,
            'total_spent' => (float) $profile->total_spent,
        ];
    }

    /**
     * @return array<string, string|int|float|null>
     */
    public function loyaltySummary(CustomerProfile $profile): array
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
