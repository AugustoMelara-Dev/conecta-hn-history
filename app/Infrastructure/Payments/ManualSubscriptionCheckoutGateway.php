<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

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
        int $amountMinor,
        string $idempotencyKey,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): SubscriptionCheckoutSessionResult {
        return SubscriptionCheckoutSessionResult::failure('Manual billing is active; no provider checkout session was created.');
    }

    public function retrieveSession(string $sessionId): SubscriptionCheckoutSessionResult
    {
        return SubscriptionCheckoutSessionResult::failure('Manual billing does not expose provider checkout sessions.');
    }

    public function createCustomerPortalSession(string $customerReference, string $returnUrl): SubscriptionPortalSessionResult
    {
        return SubscriptionPortalSessionResult::failure('Manual billing does not expose a customer portal.');
    }
}
