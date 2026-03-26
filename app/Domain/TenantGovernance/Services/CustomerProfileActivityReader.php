<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\Appointment;
use App\Models\CustomerLoyaltyLedger;
use App\Models\CustomerNote;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Lead;
use App\Models\Order;
use Illuminate\Support\Collection;

final class CustomerProfileActivityReader
{
    /**
     * @return Collection<int, Order>
     */
    public function orders(string $tenantId, int $profileId, int $limit = 8): Collection
    {
        return Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function appointments(string $tenantId, int $profileId, int $limit = 8): Collection
    {
        return Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('scheduled_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Lead>
     */
    public function leads(string $tenantId, int $profileId, int $limit = 8): Collection
    {
        return Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CustomerTouchpoint>
     */
    public function touchpoints(string $tenantId, int $profileId, int $limit = 8): Collection
    {
        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CustomerNote>
     */
    public function notes(string $tenantId, int $profileId, int $limit = 8): Collection
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CustomerTask>
     */
    public function tasks(string $tenantId, int $profileId, int $limit = 8): Collection
    {
        return CustomerTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, CustomerLoyaltyLedger>
     */
    public function loyalty(string $tenantId, int $profileId, int $limit = 8): Collection
    {
        return CustomerLoyaltyLedger::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_profile_id', $profileId)
            ->latest('awarded_at')
            ->limit($limit)
            ->get();
    }
}
