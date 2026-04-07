<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Lead;
use App\Models\Order;

class CustomerProfileWorkspaceRelinker
{
    public function relink(CustomerProfile $primary, CustomerProfile $duplicate): void
    {
        $duplicateId = (int) $duplicate->getKey();
        $primaryId = (int) $primary->getKey();

        Order::withoutEvents(function () use ($primaryId, $duplicateId): void {
            Order::query()
                ->withoutGlobalScopes()
                ->where('customer_profile_id', $duplicateId)
                ->update(['customer_profile_id' => $primaryId]);
        });

        Appointment::withoutEvents(function () use ($primaryId, $duplicateId): void {
            Appointment::query()
                ->withoutGlobalScopes()
                ->where('customer_profile_id', $duplicateId)
                ->update(['customer_profile_id' => $primaryId]);
        });

        Lead::withoutEvents(function () use ($primaryId, $duplicateId): void {
            Lead::query()
                ->withoutGlobalScopes()
                ->where('customer_profile_id', $duplicateId)
                ->update(['customer_profile_id' => $primaryId]);
        });

        CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update(['customer_profile_id' => $primaryId]);

        \App\Models\CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update([
                'customer_profile_id' => $primaryId,
                'assigned_user_id' => $primary->assigned_user_id ?? $duplicate->assigned_user_id,
                'location_id' => $primary->location_id ?? $duplicate->location_id,
            ]);

        \App\Models\CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update(['customer_profile_id' => $primaryId]);

        CustomerTask::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $duplicateId)
            ->update([
                'customer_profile_id' => $primaryId,
                'assigned_user_id' => $primary->assigned_user_id ?? $duplicate->assigned_user_id,
                'location_id' => $primary->location_id ?? $duplicate->location_id,
            ]);
    }
}
