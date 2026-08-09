<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\DTOs\SubscriptionSwapResult;

/**
 * SubscriptionGatewayInterface - Domain Port for Payment Provider Abstraction
 *
 * Isolates the domain from payment provider implementation details.
 * All Stripe/PayPal/Paddle logic is encapsulated in Infrastructure adapters.
 */
interface SubscriptionGatewayInterface
{
    /**
     * Swap the subscription to a new plan.
     *
     * @param  string  $subscriptionReference  Provider's subscription ID
     * @param  string  $newPlanId  Provider's plan/price ID
     * @param  string  $idempotencyKey  Stable request identity used by the provider
     */
    public function swapPlan(
        string $subscriptionReference,
        string $newPlanId,
        string $idempotencyKey,
    ): SubscriptionSwapResult;

    /**
     * Cancel a subscription.
     *
     * @param  string  $subscriptionReference  Provider's subscription ID
     * @param  bool  $immediately  Cancel immediately or at period end
     * @return bool Success status
     */
    public function cancel(string $subscriptionReference, bool $immediately = false): bool;
}
