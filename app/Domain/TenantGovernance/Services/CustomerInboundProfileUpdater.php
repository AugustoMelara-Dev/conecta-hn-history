<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class CustomerInboundProfileUpdater
{
    public function __construct(
        private readonly CustomerProfileRepositoryInterface $profiles,
    ) {}

    public function touchInboundChannel(CustomerTouchpoint $touchpoint, Carbon $occurredAt, string $channel): ?CustomerProfile
    {
        $profile = $this->profiles->findWithoutScopes((int) $touchpoint->customer_profile_id);

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        $profile->forceFill([
            'last_inbound_reply_at' => $occurredAt,
            'last_engaged_channel' => $channel,
        ]);

        $this->profiles->save($profile);

        return $profile;
    }
}
