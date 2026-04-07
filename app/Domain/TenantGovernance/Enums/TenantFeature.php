<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Enums;

enum TenantFeature: string
{
    case ADVANCED_CATALOG = 'advanced_catalog';
    case MARKETING_SUITE = 'marketing_suite';
    case MARKETING_CAMPAIGNS = 'marketing_campaigns';
    case PROMOTIONAL_COUPONS = 'promotional_coupons';
    case LOYALTY_PROGRAM = 'loyalty_program';
    case LOYALTY_REWARDS = 'loyalty_rewards';
    case ADVANCED_BRANDING = 'advanced_branding';
    case UPGRADE_PROMPT = 'upgrade_prompt';
}
