<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\CustomerTouchpointEvent;
use Illuminate\Support\Carbon;

interface CustomerTouchpointEventRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function firstOrCreate(array $identity, array $attributes): CustomerTouchpointEvent;

    public function findExisting(int $touchpointId, string $provider, string $eventType, Carbon $occurredAt): ?CustomerTouchpointEvent;
}
