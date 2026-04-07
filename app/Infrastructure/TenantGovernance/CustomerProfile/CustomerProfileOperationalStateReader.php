<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;

final class CustomerProfileOperationalStateReader
{
    /**
     * @return array<string, mixed>
     */
    public function read(CustomerProfile $profile): array
    {
        return [
            'preferred_channel' => $profile->preferred_channel,
            'manual_segment' => $profile->manual_segment,
            'last_contacted_at' => $profile->last_contacted_at,
            'assigned_user_id' => $profile->assigned_user_id,
            'location_id' => $profile->location_id,
            'marketing_opt_in' => (bool) $profile->marketing_opt_in,
            'whatsapp_opt_in' => (bool) $profile->whatsapp_opt_in,
            'email_opt_in' => (bool) $profile->email_opt_in,
            'tags' => is_array($profile->tags) ? $profile->tags : [],
            'crm_notes' => $profile->crm_notes,
        ];
    }
}
