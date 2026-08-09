<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing;

use App\Application\Billing\Results\BillingCheckoutResult;
use App\Application\Billing\Services\BillingCheckoutService;
use App\Domain\Billing\DTOs\SubscriptionCheckoutSessionResult;
use App\Domain\Billing\DTOs\SubscriptionPortalSessionResult;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Infrastructure\Billing\Persistence\ConfigPlanRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BillingCheckoutServiceTest extends TestCase
{
    #[Test]
    public function manual_provider_returns_manual_pending_without_calling_checkout_gateway(): void
    {
        config()->set('services.payment.provider', 'generic');
        config()->set('subscriptions.plans.pro.price', 49900);

        $gateway = new RecordingSubscriptionCheckoutGateway;
        $repository = new InMemorySubscriptionIntentRepository;

        $service = new BillingCheckoutService(
            planRepository: new ConfigPlanRepository,
            intentRepository: $repository,
            subscriptionCheckoutGateway: $gateway,
            uuidGenerator: new FixedUuidGenerator('intent-manual'),
        );

        $result = $service->start('tenant-1', 'pro', 'upgrade-1');

        self::assertSame(BillingCheckoutResult::STATUS_MANUAL_PENDING, $result->status);
        self::assertSame('generic', $result->provider);
        self::assertSame('intent-manual', $result->subscriptionIntentId);
        self::assertFalse($gateway->createCalled);
        self::assertSame('PENDING', $repository->rows['intent-manual']['status']);
    }

    #[Test]
    public function stripe_provider_creates_subscription_checkout_session_with_local_intent_metadata(): void
    {
        config()->set('services.payment.provider', 'stripe');
        config()->set('services.stripe.price_pro', 'price_pro_test');
        config()->set('subscriptions.plans.pro.price', 49900);

        $gateway = new RecordingSubscriptionCheckoutGateway;
        $repository = new InMemorySubscriptionIntentRepository;

        $service = new BillingCheckoutService(
            planRepository: new ConfigPlanRepository,
            intentRepository: $repository,
            subscriptionCheckoutGateway: $gateway,
            uuidGenerator: new FixedUuidGenerator('intent-stripe'),
        );

        $result = $service->start('tenant-1', 'pro', 'upgrade-1');

        self::assertSame(BillingCheckoutResult::STATUS_REQUIRES_PAYMENT, $result->status);
        self::assertSame('stripe', $result->provider);
        self::assertSame('cs_test_123', $result->providerSessionId);
        self::assertSame('https://checkout.stripe.test/session', $result->checkoutUrl);
        self::assertTrue($gateway->createCalled);
        self::assertSame('subscription', $gateway->mode);
        self::assertSame('price_pro_test', $gateway->priceExternalId);
        self::assertSame('intent-stripe', $gateway->metadata['subscription_intent_id']);
        self::assertSame('PROCESSING', $repository->rows['intent-stripe']['status']);
        self::assertSame('cs_test_123', $repository->rows['intent-stripe']['gateway_transaction_id']);
    }
}

final class FixedUuidGenerator implements UuidGeneratorInterface
{
    public function __construct(private readonly string $uuid) {}

    public function generate(): string
    {
        return $this->uuid;
    }
}

final class InMemorySubscriptionIntentRepository implements SubscriptionIntentRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $rows = [];

    public function insertOrIgnore(array $attributes): bool
    {
        if (isset($this->rows[(string) $attributes['id']])) {
            return false;
        }

        $this->rows[(string) $attributes['id']] = $attributes;

        return true;
    }

    public function findByTenantAndIdempotencyKey(string $tenantId, string $clientIdempotencyKey): ?object
    {
        foreach ($this->rows as $row) {
            if ($row['tenant_id'] === $tenantId && $row['client_idempotency_key'] === $clientIdempotencyKey) {
                return (object) $row;
            }
        }

        return null;
    }

    public function updateStatus(string $intentId, array $attributes): void
    {
        $this->rows[$intentId] = array_merge($this->rows[$intentId], $attributes);
    }

    public function findPendingOlderThan(string $tenantId, string $olderThan): iterable
    {
        return [];
    }
}

final class RecordingSubscriptionCheckoutGateway implements SubscriptionCheckoutGatewayInterface
{
    public bool $createCalled = false;

    public ?string $mode = null;

    public ?string $priceExternalId = null;

    /** @var array<string, string> */
    public array $metadata = [];

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
        $this->createCalled = true;
        $this->mode = 'subscription';
        $this->priceExternalId = $priceExternalId;
        $this->metadata = $metadata;

        return SubscriptionCheckoutSessionResult::requiresPayment(
            sessionId: 'cs_test_123',
            checkoutUrl: 'https://checkout.stripe.test/session',
        );
    }

    public function retrieveSession(string $sessionId): SubscriptionCheckoutSessionResult
    {
        return SubscriptionCheckoutSessionResult::paid($sessionId);
    }

    public function createCustomerPortalSession(string $customerId, string $returnUrl): SubscriptionPortalSessionResult
    {
        return SubscriptionPortalSessionResult::success('bps_test_123', 'https://billing.stripe.test/session');
    }
}
