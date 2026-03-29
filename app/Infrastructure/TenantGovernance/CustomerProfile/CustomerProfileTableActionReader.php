<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;

final class CustomerProfileTableActionReader
{
    public function __construct(
        private readonly CustomerProfileActionFormReader $actionFormReader,
        private readonly CustomerProfileActionCopyReader $actionCopyReader,
    ) {}

    public function canRedeemReward(CustomerProfile $profile): bool
    {
        return $this->actionFormReader->canRedeemReward($profile);
    }

    /**
     * @return array<string, string>
     */
    public function rewardOptions(CustomerProfile $profile): array
    {
        return $this->actionFormReader->rewardOptions($profile);
    }

    /**
     * @return array<string, mixed>
     */
    public function emailFollowUpForm(CustomerProfile $profile): array
    {
        return $this->actionFormReader->emailFollowUpForm($profile);
    }

    public function canEmailFollowUp(CustomerProfile $profile): bool
    {
        return $this->actionFormReader->canEmailFollowUp($profile);
    }

    public function canWhatsappFollowUp(CustomerProfile $profile): bool
    {
        return $this->actionFormReader->canWhatsappFollowUp($profile);
    }

    public function canTriggerReactivation(CustomerProfile $profile): bool
    {
        return $this->actionFormReader->canTriggerReactivation($profile);
    }

    public function emailFollowUpDescription(): string
    {
        return $this->actionCopyReader->emailFollowUpDescription();
    }

    public function whatsappFollowUpDescription(): string
    {
        return $this->actionCopyReader->whatsappFollowUpDescription();
    }

    public function reactivationDescription(): string
    {
        return $this->actionCopyReader->reactivationDescription();
    }
}
