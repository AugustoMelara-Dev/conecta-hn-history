<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileActionFormReader
{
    public function __construct(
        private readonly CustomerLoyaltyManager $loyaltyManager,
        private readonly CustomerAutomationManager $automationManager,
    ) {}

    public function canRedeemReward(CustomerProfile $profile): bool
    {
        return $this->loyaltyManager->hasRedeemableReward($profile);
    }

    /**
     * @return array<string, string>
     */
    public function rewardOptions(CustomerProfile $profile): array
    {
        return $this->loyaltyManager->rewardOptionsForProfile($profile);
    }

    /**
     * @return array<string, mixed>
     */
    public function emailFollowUpForm(CustomerProfile $profile): array
    {
        return [
            'automation_key' => 'manual_follow_up',
            ...$this->automationManager->preview($profile, 'manual_follow_up'),
        ];
    }
}
