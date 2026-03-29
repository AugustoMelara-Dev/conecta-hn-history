<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Domain\TenantGovernance\Services\CustomerLoyaltyManager;
use App\Models\CustomerProfile;

final class CustomerProfileActionFormReader
{
    public function __construct(
        private readonly CustomerLoyaltyManager $loyaltyManager,
        private readonly CustomerAutomationManager $automationManager,
        private readonly CustomerProfileActionVisibilityReader $visibilityReader,
    ) {}

    public function canRedeemReward(CustomerProfile $profile): bool
    {
        return $this->visibilityReader->canRedeemReward($profile);
    }

    public function canEmailFollowUp(CustomerProfile $profile): bool
    {
        return $this->visibilityReader->canEmailFollowUp($profile);
    }

    public function canWhatsappFollowUp(CustomerProfile $profile): bool
    {
        return $this->visibilityReader->canWhatsappFollowUp($profile);
    }

    public function canTriggerReactivation(CustomerProfile $profile): bool
    {
        return $this->visibilityReader->canTriggerReactivation($profile);
    }

    /**
     * @return array<string, string>
     */
    public function rewardOptions(CustomerProfile $profile): array
    {
        return collect($this->loyaltyManager->rewardOptionsForProfile($profile))
            ->mapWithKeys(static fn (string $label, int|string $key): array => [(string) $key => $label])
            ->all();
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
