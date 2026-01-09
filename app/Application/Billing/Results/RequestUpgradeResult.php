<?php

declare(strict_types=1);

namespace App\Application\Billing\Results;

use Illuminate\Http\Request;

/**
 * RequestUpgradeResult - Pure Immutable DTO for Plan Upgrade Requests
 */
final readonly class RequestUpgradeResult
{
    public function __construct(
        public int $tenantId,
        public string $plan,
        public string $paymentMethod
    ) {}

    /**
     * Factory method to create DTO from validated request.
     */
    public static function fromRequest(Request $request, int $tenantId): self
    {
        $validated = $request->validated();

        return new self(
            tenantId: $tenantId,
            plan: $validated['plan'],
            paymentMethod: $validated['payment_method']
        );
    }
}
