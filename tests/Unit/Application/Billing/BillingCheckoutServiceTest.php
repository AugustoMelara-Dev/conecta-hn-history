<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Billing;

use App\Application\Billing\Results\BillingCheckoutResult;
use App\Application\Billing\Services\BillingCheckoutService;
use App\Domain\Billing\DTOs\SubscriptionCheckoutSessionResult;
use App\Domain\Billing\DTOs\SubscriptionPortalSessionResult;
use App\Domain\Billing\Entities\PlanEntity;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BillingCheckoutServiceTest extends TestCase
{
    #[Test]
    public function it_returns_manual_pending_without_creating_provider_payment_intent_when_provider_is_generic(): void
    {
        config()->set('services.payment.provider', 'generic');

        $checkoutGateway = new RecordingSubscriptionCheckoutGateway;
        $repository = new InMemorySubscriptionIntentRepository;

        $result = $this->service($repository, $checkoutGateway)->start(
            tenantId: 'tenant-1',
            planIdentifier: 'pro',
            clientIdempotencyKey: 'client-key-1',
        );

        self::assertSame(BillingCheckoutResult::STATUS_MANUAL_PENDING, $result->status);
        self::assertSame('generic', $result->provider);
        self::assertSame(0, $checkoutGateway->checkoutCalls);
        self::assertSame('generic', $repository->rows['intent-1']['gateway_provider']);
    }

    #[Test]
    public function it_creates_stripe_checkout_session_and_marks_local_intent_processing(): void
    {
        config()->set('services.payment.provider', 'stripe');

        $checkoutGateway = new RecordingSubscriptionCheckoutGateway;
        $repository = new InMemorySubscriptionIntentRepository;

        $result = $this->service($repository, $checkoutGateway)->start(
            tenantId: 'tenant-1',
            planIdentifier: 'pro',
            clientIdempotencyKey: 'stripe-key-1',
        );

        self::assertSame(BillingCheckoutResult::STATUS_REQUIRES_PAYMENT, $result->status);
        self::assertSame('stripe', $result->provider);
        self::assertNull($result->paymentIntentId);
        self::assertSame('cs_test_stripe-key-1', $result->providerSessionId);
        self::assertSame('https://checkout.stripe.test/stripe-key-1', $result->checkoutUrl);
        self::assertSame('secret_stripe-key-1', $result->clientSecret);
        self::assertSame(1, $checkoutGateway->checkoutCalls);
        self::assertSame('PROCESSING', $repository->rows['intent-1']['status']);
        self::assertSame('cs_test_stripe-key-1', $repository->rows['intent-1']['gateway_transaction_id']);
        self::assertSame('intent-1', $checkoutGateway->lastMetadata['subscription_intent_id']);
        self::assertSame('plan_pro', $checkoutGateway->lastPriceExternalId);
        self::assertSame(2900, $checkoutGateway->lastAmountMinor);
    }

    private function service(
        InMemorySubscriptionIntentRepository $repository,
        ?RecordingSubscriptionCheckoutGateway $checkoutGateway = null,
    ): BillingCheckoutService {
        return new BillingCheckoutService(
            new StaticPlanRepository,
            $repository,
            $checkoutGateway ?? new RecordingSubscriptionCheckoutGateway,
            new SequentialUuidGenerator,
        );
    }
}

final class StaticPlanRepository implements PlanRepositoryInterface
{
    public function findByIdentifier(string $planIdentifier): ?PlanEntity
    {
        if ($planIdentifier !== 'pro') {
            return null;
        }

        return new PlanEntity(
            id: 'pro',
            name: 'Pro',
            price: new Money(2900, 'HNL'),
            externalGatewayId: 'plan_pro',
        );
    }

    public function getAllActive(): array
    {
        return [$this->findByIdentifier('pro')];
    }
}

final class InMemorySubscriptionIntentRepository implements SubscriptionIntentRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $rows = [];

    public function insertOrIgnore(array $attributes): bool
    {
        $key = $attributes['tenant_id'].'|'.$attributes['client_idempotency_key'];

        foreach ($this->rows as $row) {
            if ($key === $row['tenant_id'].'|'.$row['client_idempotency_key']) {
                return false;
            }
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
    public int $checkoutCalls = 0;

    public string $lastPriceExternalId = '';

    public int $lastAmountMinor = 0;

    /** @var array<string, string> */
    public array $lastMetadata = [];

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
        $this->checkoutCalls++;
        $this->lastPriceExternalId = $priceExternalId;
        $this->lastAmountMinor = $amountMinor;
        $this->lastMetadata = $metadata;

        return SubscriptionCheckoutSessionResult::requiresPayment(
            sessionId: 'cs_test_'.$idempotencyKey,
            checkoutUrl: 'https://checkout.stripe.test/'.$idempotencyKey,
            clientSecret: 'secret_'.$idempotencyKey,
        );
    }

    public function retrieveSession(string $sessionId): SubscriptionCheckoutSessionResult
    {
        return SubscriptionCheckoutSessionResult::requiresPayment($sessionId, 'https://checkout.stripe.test/'.$sessionId);
    }

    public function createCustomerPortalSession(string $customerReference, string $returnUrl): SubscriptionPortalSessionResult
    {
        return SubscriptionPortalSessionResult::success('bps_test', 'https://billing.stripe.test/session');
    }
}

final class SequentialUuidGenerator implements UuidGeneratorInterface
{
    private int $next = 1;

    public function generate(): string
    {
        return 'intent-'.$this->next++;
    }
}
