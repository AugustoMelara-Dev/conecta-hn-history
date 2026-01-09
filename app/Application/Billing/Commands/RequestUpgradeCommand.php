<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

/**
 * RequestUpgradeCommand - Immutable Input Contract for Plan Upgrade
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY AGNOSTIC: No Stripe/PayPal references - pure domain intent.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RequestUpgradeCommand
{
    public function __construct(
        /** Tenant requesting the upgrade */
        public int $tenantId,
        
        /** Internal plan identifier (pro, premium, etc.) */
        public string $newPlanIdentifier,
        
        /** Optional new payment method ID */
        public ?string $paymentMethodId = null,
        
        /** Payment method name for manual billing */
        public ?string $paymentMethod = null,
    ) {}
}
