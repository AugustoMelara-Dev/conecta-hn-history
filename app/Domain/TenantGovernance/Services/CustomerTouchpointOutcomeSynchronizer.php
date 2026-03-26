<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerConversationRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerConversation;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;

final class CustomerTouchpointOutcomeSynchronizer
{
    public function __construct(
        private readonly CustomerJourneyManager $journeyManager,
        private readonly CustomerProfileRepositoryInterface $profiles,
        private readonly CustomerConversationRepositoryInterface $conversations,
    ) {}

    public function sync(CustomerTouchpoint $touchpoint): void
    {
        $conversation = $touchpoint->customer_conversation_id !== null
            ? $this->conversations->findWithoutScopes((int) $touchpoint->customer_conversation_id)
            : null;
        $profile = $this->profiles->findWithoutScopes((int) $touchpoint->customer_profile_id)?->loadMissing('tenant');

        if ($conversation instanceof CustomerConversation && $profile instanceof CustomerProfile) {
            $this->journeyManager->sync($conversation, $profile);
        }
    }
}
