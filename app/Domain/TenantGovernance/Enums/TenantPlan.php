<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Enums;

enum TenantPlan: string
{
    case SEMILLA = 'semilla';
    case PRO = 'pro';
    case ENTERPRISE = 'enterprise';
}
