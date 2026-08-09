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
     * Manual billing accepts the request but never reports a completed charge.
     * The tenant plan remains unchanged until an administrator approves evidence.
     */
    public function swapPlan(
        string $subscriptionReference,
        string $newPlanId,
        string $idempotencyKey,
    ): SubscriptionSwapResult {
        Log::info('Manual subscription swap initiated', [
            'subscription_reference' => $subscriptionReference,
            'new_plan' => $newPlanId,
            'idempotency_fingerprint' => substr(hash('sha256', $idempotencyKey), 0, 12),
        ]);

        return SubscriptionSwapResult::pending(
            transactionId: 'MANUAL-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 24)),
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

    public function verifyStatus(string $reference): string
    {
        Log::info('Manual subscription status check', [
            'reference' => $reference,
        ]);

        return 'PENDING';
    }
}
