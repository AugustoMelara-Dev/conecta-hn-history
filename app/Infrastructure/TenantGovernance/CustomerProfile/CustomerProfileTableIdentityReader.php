<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;

final class CustomerProfileTableIdentityReader
{
    public function customerIdentity(CustomerProfile $profile): string
    {
        return collect([$profile->email, $profile->phone])->filter()->implode(' | ');
    }

    public function ownerLabel(CustomerProfile $profile): string
    {
        return $profile->assigned_owner_label;
    }

    public function locationLabel(CustomerProfile $profile): string
    {
        return $profile->location_label;
    }

    public function lastActivityCopy(CustomerProfile $profile): string
    {
        return $profile->preferred_channel_label;
    }
}
