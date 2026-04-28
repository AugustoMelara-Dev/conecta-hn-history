<?php

declare(strict_types=1);

namespace App\Application\Billing\Services;

use App\Application\Billing\Results\BillingCheckoutResult;
use App\Domain\Billing\Entities\SubscriptionIntent;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class BillingCheckoutService
{
    public function __construct(
        private PlanRepositoryInterface $planRepository,
        private SubscriptionIntentRepositoryInterface $intentRepository,
        private SubscriptionCheckoutGatewayInterface $subscriptionCheckoutGateway,
        private UuidGeneratorInterface $uuidGenerator,
    ) {}

    public function start(
        string $tenantId,
        string $planIdentifier,
        string $clientIdempotencyKey,
        ?string $currency = null,
    ): BillingCheckoutResult {
        $plan = $this->planRepository->findByIdentifier($planIdentifier);

        if ($plan === null) {
            throw new InvalidArgumentException("Invalid plan: {$planIdentifier}");
        }

        $provider = strtolower((string) config('services.payment.provider', 'generic'));
        $intentId = $this->resolveIntentId(
            tenantId: $tenantId,
            planIdentifier: $plan->id,
            provider: $provider,
            clientIdempotencyKey: $clientIdempotencyKey,
        );

        if ($provider !== 'stripe') {
            return new BillingCheckoutResult(
                status: BillingCheckoutResult::STATUS_MANUAL_PENDING,
                tenantId: $tenantId,
                planIdentifier: $plan->id,
                subscriptionIntentId: $intentId,
                provider: $provider !== '' ? $provider : 'generic',
                message: 'Manual billing is active; upload payment evidence for review.',
            );
        }

        $metadata = [
            'tenant_id' => $tenantId,
            'subscription_intent_id' => $intentId,
            'client_idempotency_key' => $clientIdempotencyKey,
            'plan_identifier' => $plan->id,
        ];

        $checkoutSession = $this->subscriptionCheckoutGateway->createCheckoutSession(
            tenantId: $tenantId,
            planIdentifier: $plan->id,
            priceExternalId: $plan->externalGatewayId,
            currency: $currency ?: $plan->currency(),
            idempotencyKey: $clientIdempotencyKey,
            successUrl: $this->billingReturnUrl($tenantId, true),
            cancelUrl: $this->billingReturnUrl($tenantId, false),
            metadata: $metadata,
        );

        if (! $checkoutSession->success) {
            $this->intentRepository->updateStatus($intentId, [
                'status' => SubscriptionIntent::STATUS_FAILED,
                'error_reason' => $checkoutSession->errorMessage,
            ]);

            return new BillingCheckoutResult(
                status: BillingCheckoutResult::STATUS_FAILED,
                tenantId: $tenantId,
                planIdentifier: $plan->id,
                subscriptionIntentId: $intentId,
                provider: 'stripe',
                message: $checkoutSession->errorMessage,
            );
        }

        $this->intentRepository->updateStatus($intentId, [
            'status' => $checkoutSession->status === BillingCheckoutResult::STATUS_PAID
                ? SubscriptionIntent::STATUS_COMPLETED
                : SubscriptionIntent::STATUS_PROCESSING,
            'gateway_transaction_id' => $checkoutSession->sessionId,
        ]);

        return new BillingCheckoutResult(
            status: $checkoutSession->status,
            tenantId: $tenantId,
            planIdentifier: $plan->id,
            subscriptionIntentId: $intentId,
            provider: 'stripe',
            providerSessionId: $checkoutSession->sessionId,
            checkoutUrl: $checkoutSession->checkoutUrl,
            clientSecret: $checkoutSession->clientSecret,
        );
    }

    private function billingReturnUrl(string $tenantId, bool $success): string
    {
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $status = $success ? 'success' : 'cancelled';

        return "{$baseUrl}/dashboard?billing={$status}&tenant={$tenantId}";
    }

    private function resolveIntentId(
        string $tenantId,
        string $planIdentifier,
        string $provider,
        string $clientIdempotencyKey,
    ): string {
        $generatedIntentId = $this->uuidGenerator->generate();
        $now = CarbonImmutable::now();

        $inserted = $this->intentRepository->insertOrIgnore([
            'id' => $generatedIntentId,
            'tenant_id' => $tenantId,
            'client_idempotency_key' => $clientIdempotencyKey,
            'plan_identifier' => $planIdentifier,
            'gateway_provider' => $provider !== '' ? $provider : 'generic',
            'status' => SubscriptionIntent::STATUS_PENDING,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted) {
            return $generatedIntentId;
        }

        $existing = $this->intentRepository->findByTenantAndIdempotencyKey(
            $tenantId,
            $clientIdempotencyKey,
        );

        if ($existing === null) {
            throw new \RuntimeException('Duplicate subscription intent detected but could not be recovered.');
        }

        return (string) $existing->id;
    }
}
