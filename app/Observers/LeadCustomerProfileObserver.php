<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Models\Lead;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class LeadCustomerProfileObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Lead $lead): void
    {
        app(CustomerProfileManager::class)->syncLead(
            $lead,
            $this->originalProfileId($lead),
        );
    }

    public function deleted(Lead $lead): void
    {
        app(CustomerProfileManager::class)->syncProfileById(
            $this->originalProfileId($lead) ?? $this->currentProfileId($lead),
        );
    }

    private function originalProfileId(Lead $lead): ?int
    {
        $value = $lead->getOriginal('customer_profile_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function currentProfileId(Lead $lead): ?int
    {
        $value = $lead->getAttribute('customer_profile_id');

        return is_numeric($value) ? (int) $value : null;
    }
}
