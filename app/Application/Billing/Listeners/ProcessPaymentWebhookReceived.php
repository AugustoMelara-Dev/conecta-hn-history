<?php

declare(strict_types=1);

namespace App\Application\Billing\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Billing\Events\PaymentWebhookReceived;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Models\SubscriptionPayment;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class ProcessPaymentWebhookReceived
{
    public function __construct(
        private LoggerInterface $logger,
        private TenantContext $tenantContext,
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function handle(PaymentWebhookReceived $event): void
    {
        $webhook = $event->webhook;
        $shouldResetContext = false;

        if (! $this->tenantContext->isInitialized()) {
            $this->tenantContext->setTenant($webhook->tenantId);
            $shouldResetContext = true;
        }

        try {
            if (! $webhook->isSuccessful()) {
                $this->logger->info('Payment webhook ignored: event type is not billable.', [
                    'tenant_id' => $webhook->tenantId,
                    'event_type' => $webhook->eventType,
                    'provider_id' => $webhook->providerId,
                ]);

                return;
            }

            if ($webhook->amountInCents() <= 0) {
                $this->logger->warning('Payment webhook rejected: successful event has no positive amount.', [
                    'tenant_id' => $webhook->tenantId,
                    'event_type' => $webhook->eventType,
                    'provider_id' => $webhook->providerId,
                    'payment_reference' => $webhook->paymentReference(),
                ]);

                return;
            }

            $payment = DB::transaction(function () use ($webhook) {
                if ($webhook->isStripe() && ! $this->stripeWebhookMatchesLocalIntent($webhook)) {
                    return null;
                }

                $payment = $this->findExistingPayment($webhook->tenantId, $webhook->providerId, $webhook->paymentReference(), $webhook->providerName());

                if ($payment === null) {
                    $payment = $this->createPayment($webhook);
                }

                return $payment;
            }, 3);

            if (! $payment instanceof SubscriptionPayment) {
                return;
            }

            if ($payment->subscription_extended_at !== null) {
                $this->logger->info('Payment webhook replay recovered an already-settled payment.', [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                    'idempotency_key' => $payment->idempotency_key,
                ]);

                return;
            }

            if ($payment->isPending()) {
                $payment->approve();
                $this->markStripeIntentCompleted($webhook);

                $this->logger->info('Payment webhook materialized and approved subscription payment.', [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                    'reference_number' => $payment->reference_number,
                    'idempotency_key' => $payment->idempotency_key,
                ]);

                return;
            }

            if ($payment->isApproved()) {
                $this->markStripeIntentCompleted($webhook);

                event(\App\Events\PaymentApproved::fromPayment(
                    tenantId: (string) $payment->tenant_id,
                    paymentId: (string) $payment->id,
                    reviewerId: null,
                ));

                $this->logger->warning('Payment webhook recovered an approved payment that was not yet settled.', [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                ]);

                return;
            }

            $this->logger->warning('Payment webhook found payment in non-billable state; skipping.', [
                'payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id,
                'status' => $payment->status,
            ]);
        } finally {
            if ($shouldResetContext) {
                $this->tenantContext->reset();
            }
        }
    }

    private function createPayment(PaymentWebhook $webhook): SubscriptionPayment
    {
        try {
            return SubscriptionPayment::forceCreate([
                'id' => (string) Str::uuid(),
                'tenant_id' => $webhook->tenantId,
                'amount' => $webhook->amountInCents() / 100,
                'bank' => $webhook->providerName(),
                'reference_number' => $webhook->paymentReference(),
                'proof_path' => '',
                'status' => SubscriptionPayment::STATUS_PENDING,
                'idempotency_key' => $webhook->providerId,
            ]);
        } catch (Throwable $e) {
            if (! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }

            $existing = $this->findExistingPayment(
                $webhook->tenantId,
                $webhook->providerId,
                $webhook->paymentReference(),
                $webhook->providerName(),
            );

            if ($existing === null) {
                throw $e;
            }

            return $existing;
        }
    }

    private function findExistingPayment(
        string $tenantId,
        string $idempotencyKey,
        string $referenceNumber,
        string $bank,
    ): ?SubscriptionPayment {
        return SubscriptionPayment::query()
            ->where(function ($query) use ($tenantId, $idempotencyKey, $referenceNumber, $bank): void {
                $query->where(function ($inner) use ($tenantId, $idempotencyKey): void {
                    $inner->where('tenant_id', $tenantId)
                        ->where('idempotency_key', $idempotencyKey);
                })->orWhere(function ($inner) use ($tenantId, $bank, $referenceNumber): void {
                    $inner->where('tenant_id', $tenantId)
                        ->where('bank', $bank)
                        ->where('reference_number', $referenceNumber);
                });
            })
            ->lockForUpdate()
            ->first();
    }

    private function isUniqueConstraintViolation(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        return (string) $e->getCode() === '23000'
            || (string) ($e->errorInfo[0] ?? '') === '23000'
            || ($e->errorInfo[1] ?? null) === 1062;
    }

    private function stripeWebhookMatchesLocalIntent(PaymentWebhook $webhook): bool
    {
        $intent = $this->findLocalIntentForWebhook($webhook);

        if ($intent === null) {
            $this->logger->warning('Stripe webhook ignored: no matching local subscription intent.', [
                'tenant_id' => $webhook->tenantId,
                'provider_id' => $webhook->providerId,
                'payment_reference' => $webhook->paymentReference(),
                'subscription_intent_id' => $webhook->subscriptionIntentId(),
                'client_idempotency_key_present' => $webhook->clientIdempotencyKey() !== null,
            ]);

            return false;
        }

        if ((string) $intent->tenant_id !== $webhook->tenantId) {
            $this->logger->warning('Stripe webhook ignored: tenant mismatch against local intent.', [
                'webhook_tenant_id' => $webhook->tenantId,
                'intent_tenant_id' => (string) $intent->tenant_id,
                'intent_id' => (string) $intent->id,
            ]);

            return false;
        }

        if (! in_array((string) $intent->status, ['PENDING', 'PROCESSING'], true)) {
            $this->logger->info('Stripe webhook ignored: local subscription intent is already terminal.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'status' => (string) $intent->status,
            ]);

            return false;
        }

        if ((string) $intent->gateway_provider !== 'stripe') {
            $this->logger->warning('Stripe webhook ignored: local intent provider mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'gateway_provider' => (string) $intent->gateway_provider,
            ]);

            return false;
        }

        $plan = $this->planRepository->findByIdentifier((string) $intent->plan_identifier);
        if ($plan === null) {
            $this->logger->warning('Stripe webhook ignored: local intent references unknown plan.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'plan_identifier' => (string) $intent->plan_identifier,
            ]);

            return false;
        }

        $metadataPlan = $webhook->planIdentifier();
        if ($metadataPlan !== null && $metadataPlan !== (string) $intent->plan_identifier) {
            $this->logger->warning('Stripe webhook ignored: plan metadata mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_plan' => (string) $intent->plan_identifier,
                'received_plan' => $metadataPlan,
            ]);

            return false;
        }

        if (strtolower($webhook->currency()) !== strtolower($plan->price->currency)) {
            $this->logger->warning('Stripe webhook ignored: currency mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_currency' => $plan->price->currency,
                'received_currency' => $webhook->currency(),
            ]);

            return false;
        }

        if ($webhook->amountInCents() !== $plan->price->amount) {
            $this->logger->warning('Stripe webhook ignored: amount mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_amount_cents' => $plan->price->amount,
                'received_amount_cents' => $webhook->amountInCents(),
            ]);

            return false;
        }

        $gatewayTransactionId = $intent->gateway_transaction_id;
        if (is_string($gatewayTransactionId) && $gatewayTransactionId !== '' && $gatewayTransactionId !== $webhook->paymentReference()) {
            $this->logger->warning('Stripe webhook ignored: payment intent mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_payment_reference' => $gatewayTransactionId,
                'received_payment_reference' => $webhook->paymentReference(),
            ]);

            return false;
        }

        DB::table('subscription_intents')
            ->where('id', (string) $intent->id)
            ->update([
                'status' => 'PROCESSING',
                'gateway_transaction_id' => $webhook->paymentReference(),
                'updated_at' => now(),
            ]);

        return true;
    }

    private function markStripeIntentCompleted(PaymentWebhook $webhook): void
    {
        if (! $webhook->isStripe()) {
            return;
        }

        $intentId = $webhook->subscriptionIntentId();
        $clientKey = $webhook->clientIdempotencyKey();

        if ($intentId === null && $clientKey === null) {
            return;
        }

        $query = DB::table('subscription_intents')
            ->where('tenant_id', $webhook->tenantId);

        if ($intentId !== null) {
            $query->where('id', $intentId);
        } else {
            $query->where('client_idempotency_key', $clientKey);
        }

        $query->update([
            'status' => 'COMPLETED',
            'gateway_transaction_id' => $webhook->paymentReference(),
            'updated_at' => now(),
        ]);
    }

    private function findLocalIntentForWebhook(PaymentWebhook $webhook): ?object
    {
        $query = DB::table('subscription_intents')
            ->where('tenant_id', $webhook->tenantId)
            ->lockForUpdate();

        $subscriptionIntentId = $webhook->subscriptionIntentId();
        if ($subscriptionIntentId !== null) {
            return (clone $query)->where('id', $subscriptionIntentId)->first();
        }

        $clientKey = $webhook->clientIdempotencyKey();
        if ($clientKey !== null) {
            return (clone $query)->where('client_idempotency_key', $clientKey)->first();
        }

        return null;
    }
}
