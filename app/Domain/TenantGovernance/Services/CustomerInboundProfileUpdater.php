<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class CustomerInboundProfileUpdater
{
    private ?bool $supportsLastInboundReplyAt = null;

    private ?bool $supportsLastEngagedChannel = null;

    public function __construct(
        private readonly CustomerProfileRepositoryInterface $profiles,
    ) {}

    public function touchInboundChannel(CustomerTouchpoint $touchpoint, Carbon $occurredAt, string $channel): ?CustomerProfile
    {
        $profile = $this->profiles->findWithoutScopes((int) $touchpoint->customer_profile_id);

        if (! $profile instanceof CustomerProfile) {
            return null;
        }

        $payload = [];

        if ($this->hasLastEngagedChannelColumn()) {
            $payload['last_engaged_channel'] = $channel;
        }

        if ($this->hasLastInboundReplyAtColumn()) {
            $payload['last_inbound_reply_at'] = $occurredAt;
        }

        if ($payload !== []) {
            $profile->forceFill($payload);
            $this->profiles->save($profile);
        }

        return $profile;
    }

    private function hasLastInboundReplyAtColumn(): bool
    {
        if ($this->supportsLastInboundReplyAt === null) {
            $this->supportsLastInboundReplyAt = Schema::hasTable('customer_profiles')
                && Schema::hasColumn('customer_profiles', 'last_inbound_reply_at');
        }

        return $this->supportsLastInboundReplyAt;
    }

    private function hasLastEngagedChannelColumn(): bool
    {
        if ($this->supportsLastEngagedChannel === null) {
            $this->supportsLastEngagedChannel = Schema::hasTable('customer_profiles')
                && Schema::hasColumn('customer_profiles', 'last_engaged_channel');
        }

        return $this->supportsLastEngagedChannel;
    }
}
