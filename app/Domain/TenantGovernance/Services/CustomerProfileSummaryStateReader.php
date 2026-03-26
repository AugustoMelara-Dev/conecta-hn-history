<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

final class CustomerProfileSummaryStateReader
{
    /**
     * @return array<string, string|int|float|null>
     */
    public function read(CustomerProfile $profile): array
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
}
