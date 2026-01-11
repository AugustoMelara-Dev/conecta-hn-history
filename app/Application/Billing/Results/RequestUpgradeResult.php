<?php

declare(strict_types=1);

namespace App\Application\Billing\Results;

/**
 * RequestUpgradeResult - Immutable Result DTO for Plan Upgrades
 *
 * DOMAIN: Billing
 * TYPE: Pure DTO
 * INVARIANT: Transport agnostic container for upgrade intent result.
 */
final readonly class RequestUpgradeResult
{
    /**
     * @param int $tenantId
     * @param string $plan
     * @param string $paymentMethod
     */
    public function __construct(
        public int $tenantId,
        public string $plan,
        public string $paymentMethod
    ) {}
}
