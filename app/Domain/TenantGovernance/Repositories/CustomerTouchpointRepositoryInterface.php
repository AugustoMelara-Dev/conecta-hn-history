<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\CustomerTouchpoint;

interface CustomerTouchpointRepositoryInterface
{
    public function findById(int $touchpointId): ?CustomerTouchpoint;

    public function findByProviderMessage(string $provider, string $providerMessageId): ?CustomerTouchpoint;

    public function save(CustomerTouchpoint $touchpoint): void;
}
