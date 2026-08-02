<?php

declare(strict_types=1);

namespace App\Application\Billing\Global;

use App\Domain\Billing\Global\BillingCapability;
use App\Domain\Billing\Global\EntitlementDecision;
use App\Domain\Billing\Global\GlobalTenantContext;

interface EntitlementServiceInterface
{
    public function allows(GlobalTenantContext $tenant, BillingCapability $capability): EntitlementDecision;
}
