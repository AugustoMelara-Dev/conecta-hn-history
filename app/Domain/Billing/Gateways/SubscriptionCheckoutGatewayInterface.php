<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\DTOs\SubscriptionCheckoutSessionResult;
use App\Domain\Billing\DTOs\SubscriptionPortalSessionResult;

interface SubscriptionCheckoutGatewayInterface
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function createCheckoutSession(
        string $tenantId,
        string $planIdentifier,
        string $priceExternalId,
        string $currency,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): SubscriptionCheckoutSessionResult;

    public function retrieveSession(string $sessionId): SubscriptionCheckoutSessionResult;

    public function createCustomerPortalSession(string $customerId, string $returnUrl): SubscriptionPortalSessionResult;
}
