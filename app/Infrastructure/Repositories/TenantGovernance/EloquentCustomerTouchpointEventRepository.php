<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface;
use App\Models\CustomerTouchpointEvent;
use Illuminate\Support\Carbon;

final class EloquentCustomerTouchpointEventRepository implements CustomerTouchpointEventRepositoryInterface
{
    public function firstOrCreate(array $identity, array $attributes): CustomerTouchpointEvent
    {
        return CustomerTouchpointEvent::query()
            ->withoutGlobalScopes()
            ->firstOrCreate($identity, $attributes);
    }

    public function findExisting(int $touchpointId, string $provider, string $eventType, Carbon $occurredAt): ?CustomerTouchpointEvent
    {
        return CustomerTouchpointEvent::query()
            ->withoutGlobalScopes()
            ->where('customer_touchpoint_id', $touchpointId)
            ->where('provider', $provider)
            ->where('event_type', $eventType)
            ->where('occurred_at', $occurredAt)
            ->first();
    }
}
