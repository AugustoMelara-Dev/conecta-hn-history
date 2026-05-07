<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Gateways;

use App\Domain\Billing\DTOs\SubscriptionCheckoutSessionResult;
use App\Domain\Billing\DTOs\SubscriptionPortalSessionResult;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;

final class ManualSubscriptionCheckoutGateway implements SubscriptionCheckoutGatewayInterface
{
    public function createCheckoutSession(
        string $tenantId,
        string $planIdentifier,
        string $priceExternalId,
        string $currency,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): SubscriptionCheckoutSessionResult {
        return SubscriptionCheckoutSessionResult::failure('Manual billing is active.');
    }

    public function retrieveSession(string $sessionId): SubscriptionCheckoutSessionResult
    {
        return SubscriptionCheckoutSessionResult::failure('Manual billing is active.');
    }

    public function createCustomerPortalSession(string $customerId, string $returnUrl): SubscriptionPortalSessionResult
    {
        return SubscriptionPortalSessionResult::failure('Manual billing is active.');
    }
}
