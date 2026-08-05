<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Billing\Services\MonthlyAiCreditGrantService;
use App\Domain\Identity\Events\TenantRegistered;
use App\Models\Tenant;

final readonly class GrantInitialAiCredits
{
    public function __construct(
        private MonthlyAiCreditGrantService $grants,
    ) {}

    public function handle(TenantRegistered $event): void
    {
        $tenant = Tenant::query()->find($event->tenantId);

        if ($tenant instanceof Tenant) {
            $this->grants->grant($tenant);
        }
    }
}
