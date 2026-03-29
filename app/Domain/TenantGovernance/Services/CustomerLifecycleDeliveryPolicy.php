<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;

final class CustomerLifecycleDeliveryPolicy
{
    public function resolveChannel(CustomerProfile $profile): string
    {
        if ($profile->preferred_channel === 'whatsapp' && filled($profile->phone) && $profile->whatsapp_opt_in) {
            return 'whatsapp';
        }

        if (filled($profile->email) && $profile->email_opt_in) {
            return 'email';
        }

        if (filled($profile->phone) && $profile->whatsapp_opt_in) {
            return 'whatsapp';
        }

        return filled($profile->email) ? 'email' : 'whatsapp';
    }

    public function wasRecentlyTriggered(CustomerProfile $profile, string $automationKey, string $channel): bool
    {
        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', (int) $profile->getKey())
            ->where('automation_key', $automationKey)
            ->where('channel', $channel)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
    }
}
