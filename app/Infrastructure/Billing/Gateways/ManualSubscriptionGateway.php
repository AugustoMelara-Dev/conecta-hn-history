<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Gateways;

use App\Domain\Billing\DTOs\SubscriptionSwapResult;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * ManualSubscriptionGateway - Infrastructure Adapter for Manual Billing
 *
 * Simplified gateway for systems using manual payment verification.
 * Creates pending payment records instead of automatic billing.
 *
 * NOTE: Replace with StripeSubscriptionGateway when integrating Stripe.
 */
final class ManualSubscriptionGateway implements SubscriptionGatewayInterface
{
    /**
     * {@inheritDoc}
     *
     * In manual billing mode, always returns success.
     * The actual upgrade happens when admin approves the payment.
     */
    public function swapPlan(string $subscriptionReference, string $newPlanExternalId): SubscriptionSwapResult
    {
        Log::info('Manual subscription swap initiated', [
            'subscription_reference' => $subscriptionReference,
            'new_plan' => $newPlanExternalId,
        ]);

        // Manual billing: Always succeed, upgrade activated on admin approval
        return SubscriptionSwapResult::success(
            newPeriodEnd: new \DateTimeImmutable('+30 days'),
            transactionId: 'MANUAL-'.strtoupper(uniqid()),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(string $subscriptionReference, bool $immediately = false): bool
    {
        Log::info('Manual subscription cancellation', [
            'subscription_reference' => $subscriptionReference,
            'immediately' => $immediately,
        ]);

        return true;
    }
}
