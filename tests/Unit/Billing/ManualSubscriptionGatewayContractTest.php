<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Domain\Billing\DTOs\SubscriptionSwapResult;
use App\Infrastructure\Billing\Gateways\ManualSubscriptionGateway;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManualSubscriptionGatewayContractTest extends TestCase
{
    #[Test]
    public function manual_plan_swap_is_pending_and_idempotent_instead_of_fake_success(): void
    {
        $gateway = new ManualSubscriptionGateway;

        $first = $gateway->swapPlan(
            subscriptionReference: 'tenant-subscription-1',
            newPlanId: 'pro',
            idempotencyKey: 'upgrade-intent-123',
        );
        $second = $gateway->swapPlan(
            subscriptionReference: 'tenant-subscription-1',
            newPlanId: 'pro',
            idempotencyKey: 'upgrade-intent-123',
        );

        self::assertFalse($first->success);
        self::assertTrue($first->isPending());
        self::assertSame(SubscriptionSwapResult::STATUS_PENDING, $first->status);
        self::assertSame($first->transactionId, $second->transactionId);
        self::assertStringStartsWith('MANUAL-', (string) $first->transactionId);
        self::assertNull($first->newPeriodEnd);
        self::assertNull($first->failureReason);
    }
}
