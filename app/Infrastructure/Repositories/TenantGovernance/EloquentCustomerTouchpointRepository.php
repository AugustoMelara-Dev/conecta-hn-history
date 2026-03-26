<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Models\CustomerTouchpoint;

final class EloquentCustomerTouchpointRepository implements CustomerTouchpointRepositoryInterface
{
    public function findById(int $touchpointId): ?CustomerTouchpoint
    {
        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->find($touchpointId);
    }

    public function findByProviderMessage(string $provider, string $providerMessageId): ?CustomerTouchpoint
    {
        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('provider', $provider)
            ->where('provider_message_id', $providerMessageId)
            ->first();
    }

    public function save(CustomerTouchpoint $touchpoint): void
    {
        $touchpoint->save();
    }
}
