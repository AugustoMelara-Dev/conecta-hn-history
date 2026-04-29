<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Payments;

use App\Infrastructure\Payments\Stripe\StripeSubscriptionCheckoutGateway;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class StripeSubscriptionCheckoutGatewayTest extends TestCase
{
    #[Test]
    public function it_builds_subscription_line_item_from_admin_configured_amount_when_no_price_id_is_used(): void
    {
        $lineItem = $this->lineItem(
            planIdentifier: 'pro',
            priceExternalId: 'plan_pro',
            currency: 'HNL',
            amountMinor: 64999,
        );

        self::assertArrayHasKey('price_data', $lineItem);
        self::assertSame('hnl', $lineItem['price_data']['currency']);
        self::assertSame(64999, $lineItem['price_data']['unit_amount']);
        self::assertSame(['interval' => 'month'], $lineItem['price_data']['recurring']);
        self::assertSame(1, $lineItem['quantity']);
    }

    #[Test]
    public function it_uses_configured_stripe_price_ids_when_price_id_mode_is_enabled(): void
    {
        $lineItem = $this->lineItem(
            planIdentifier: 'pro',
            priceExternalId: 'price_123',
            currency: 'HNL',
            amountMinor: 64999,
        );

        self::assertSame([
            'price' => 'price_123',
            'quantity' => 1,
        ], $lineItem);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItem(string $planIdentifier, string $priceExternalId, string $currency, int $amountMinor): array
    {
        $method = new ReflectionMethod(StripeSubscriptionCheckoutGateway::class, 'lineItem');

        return $method->invoke(
            new StripeSubscriptionCheckoutGateway,
            $planIdentifier,
            $priceExternalId,
            $currency,
            $amountMinor,
        );
    }
}
