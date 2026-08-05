<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing;

use App\Application\Billing\Contracts\PaymentWebhookSettlementInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Billing\Enums\CanonicalPaymentState;
use App\Domain\Billing\Mappers\CanonicalPaymentStateMapper;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final readonly class EloquentPaymentWebhookSettlement implements PaymentWebhookSettlementInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private PlanRepositoryInterface $planRepository,
    ) {}

    public function settle(PaymentWebhook $webhook): void
    {
        if (! $webhook->isSuccessful()) {
            if ($webhook->isStripe()) {
                $this->handleStripeNonSuccessfulEvent($webhook);
            }

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

        /** @var array{payment_id: string, tenant_id: string, reference_number: string, idempotency_key: string, recovered: bool}|null $settlement */
        $settlement = DB::transaction(function () use ($webhook): ?array {
            if (! $this->webhookMatchesLocalIntent($webhook)) {
                return null;
            }

            $payment = $this->findExistingPayment($webhook->tenantId, $webhook->providerId, $webhook->paymentReference(), $webhook->providerName());

            if ($payment === null) {
                $payment = $this->createPayment($webhook);
            }

            if (property_exists($payment, 'subscription_extended_at') && $payment->subscription_extended_at !== null) {
                return null;
            }

            $paymentState = CanonicalPaymentStateMapper::fromManualEvidence((string) $payment->status);
            if ($paymentState === CanonicalPaymentState::Pending) {
                if (! $this->approveWebhookPayment($payment)) {
                    throw new \RuntimeException("Payment {$payment->id} could not be atomically approved.");
                }
            } elseif (! $paymentState->canGrantEntitlement()) {
                $this->logger->warning('Payment webhook found payment in non-billable state; skipping.', [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                    'status' => $payment->status,
                ]);

                return null;
            }

            $this->extendTenantSubscription($webhook->tenantId);
            if (! $this->markLocalIntentCompleted($webhook)) {
                throw new \RuntimeException('Local subscription intent could not be atomically completed.');
            }

            if (! $this->markPaymentExtended((string) $payment->id)) {
                throw new \RuntimeException("Payment {$payment->id} could not be atomically marked as extended.");
            }

            $this->persistStripeTenantReferences($webhook);

            return [
                'payment_id' => (string) $payment->id,
                'tenant_id' => (string) $payment->tenant_id,
                'reference_number' => (string) $payment->reference_number,
                'idempotency_key' => (string) $payment->idempotency_key,
                'recovered' => $paymentState->canGrantEntitlement(),
            ];
        }, 3);

        if ($settlement === null) {
            return;
        }

        $this->logger->info('Payment webhook atomically settled subscription payment.', [
            'payment_id' => $settlement['payment_id'],
            'tenant_id' => $settlement['tenant_id'],
            'reference_number' => $settlement['reference_number'],
            'idempotency_key' => $settlement['idempotency_key'],
            'recovered' => $settlement['recovered'],
        ]);
    }

    private function createPayment(PaymentWebhook $webhook): object
    {
        try {
            DB::table('subscription_payments')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $webhook->tenantId,
                'amount' => $webhook->amountInCents() / 100,
                'bank' => $webhook->providerName(),
                'reference_number' => $webhook->paymentReference(),
                'proof_path' => '',
                'status' => 'pending',
                'idempotency_key' => $webhook->providerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->findExistingPayment(
                $webhook->tenantId,
                $webhook->providerId,
                $webhook->paymentReference(),
                $webhook->providerName(),
            ) ?? throw new \RuntimeException('Inserted webhook payment could not be reloaded.');
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
    ): ?object {
        return DB::table('subscription_payments')
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

    private function approveWebhookPayment(object $payment): bool
    {
        $values = [
            'status' => 'approved',
            'reviewed_at' => now(),
            'updated_at' => now(),
        ];

        return DB::table('subscription_payments')
            ->where('id', $payment->id)
            ->where('status', 'pending')
            ->update($values) === 1;
    }

    private function extendTenantSubscription(string $tenantId): void
    {
        $tenant = DB::table('tenants')
            ->where('id', $tenantId)
            ->lockForUpdate()
            ->first();

        if ($tenant === null) {
            throw new \RuntimeException("Payment webhook tenant {$tenantId} not found.");
        }

        $baseDate = $tenant->subscription_ends_at !== null
            ? \Illuminate\Support\Carbon::parse($tenant->subscription_ends_at)
            : now();

        if ($baseDate->isPast()) {
            $baseDate = now();
        }

        $values = [
            'is_active' => true,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $values['subscription_status'] = 'active';
        }

        if (Schema::hasColumn('tenants', 'subscription_ends_at')) {
            $values['subscription_ends_at'] = $baseDate->copy()->addDays(30);
        }

        if (Schema::hasColumn('tenants', 'plan_type')) {
            $values['plan_type'] = 'pro';
        }

        DB::table('tenants')
            ->where('id', $tenantId)
            ->update($values);

    }

    private function markPaymentExtended(string $paymentId): bool
    {
        if (! Schema::hasColumn('subscription_payments', 'subscription_extended_at')) {
            return true;
        }

        return DB::table('subscription_payments')
            ->where('id', $paymentId)
            ->whereNull('subscription_extended_at')
            ->update(['subscription_extended_at' => now()]) === 1;
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

    private function webhookMatchesLocalIntent(PaymentWebhook $webhook): bool
    {
        $intent = $this->findLocalIntentForWebhook($webhook);

        if ($intent === null) {
            $this->logger->warning('Payment webhook ignored: no matching local subscription intent.', [
                'tenant_id' => $webhook->tenantId,
                'provider_id' => $webhook->providerId,
                'payment_reference' => $webhook->paymentReference(),
                'subscription_intent_id' => $webhook->subscriptionIntentId(),
                'client_idempotency_key_present' => $webhook->clientIdempotencyKey() !== null,
            ]);

            return false;
        }

        if ((string) $intent->tenant_id !== $webhook->tenantId) {
            $this->logger->warning('Payment webhook ignored: tenant mismatch against local intent.', [
                'webhook_tenant_id' => $webhook->tenantId,
                'intent_tenant_id' => (string) $intent->tenant_id,
                'intent_id' => (string) $intent->id,
            ]);

            return false;
        }

        $intentState = CanonicalPaymentStateMapper::fromSubscriptionIntent((string) $intent->status);

        if (! $intentState->isInProgress()) {
            $this->logger->info('Payment webhook ignored: local subscription intent is already terminal.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'status' => (string) $intent->status,
            ]);

            return false;
        }

        if (strtolower((string) $intent->gateway_provider) !== strtolower($webhook->providerName())) {
            $this->logger->warning('Payment webhook ignored: local intent provider mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'gateway_provider' => (string) $intent->gateway_provider,
                'webhook_provider' => $webhook->providerName(),
            ]);

            return false;
        }

        $plan = $this->planRepository->findByIdentifier((string) $intent->plan_identifier);
        if ($plan === null) {
            $this->logger->warning('Payment webhook ignored: local intent references unknown plan.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'plan_identifier' => (string) $intent->plan_identifier,
            ]);

            return false;
        }

        $metadataPlan = $webhook->planIdentifier();
        if ($metadataPlan !== null && $metadataPlan !== (string) $intent->plan_identifier) {
            $this->logger->warning('Payment webhook ignored: plan metadata mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_plan' => (string) $intent->plan_identifier,
                'received_plan' => $metadataPlan,
            ]);

            return false;
        }

        if (strtolower($webhook->currency()) !== strtolower($plan->price->currency)) {
            $this->logger->warning('Payment webhook ignored: currency mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_currency' => $plan->price->currency,
                'received_currency' => $webhook->currency(),
            ]);

            return false;
        }

        if ($webhook->amountInCents() !== $plan->price->amount) {
            $this->logger->warning('Payment webhook ignored: amount mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_amount_cents' => $plan->price->amount,
                'received_amount_cents' => $webhook->amountInCents(),
            ]);

            return false;
        }

        $gatewayTransactionId = $intent->gateway_transaction_id;
        if (
            is_string($gatewayTransactionId)
            && $gatewayTransactionId !== ''
            && $gatewayTransactionId !== $webhook->paymentReference()
            && ! str_starts_with($webhook->eventType, 'invoice.')
        ) {
            $this->logger->warning('Payment webhook ignored: payment intent mismatch.', [
                'tenant_id' => $webhook->tenantId,
                'intent_id' => (string) $intent->id,
                'expected_payment_reference' => $gatewayTransactionId,
                'received_payment_reference' => $webhook->paymentReference(),
            ]);

            return false;
        }

        $claimed = DB::table('subscription_intents')
            ->where('id', (string) $intent->id)
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->update([
                'status' => 'PROCESSING',
                'gateway_transaction_id' => $webhook->paymentReference(),
                'updated_at' => now(),
            ]);

        if ($claimed === 1) {
            return true;
        }

        // MySQL reports zero affected rows when an already-PROCESSING intent is
        // written with the same reference within the same timestamp second.
        // Re-read the authoritative state so a semantic no-op is not mistaken
        // for a rejected claim, while a concurrent terminal transition remains
        // fail-closed.
        return DB::table('subscription_intents')
            ->where('id', (string) $intent->id)
            ->where('tenant_id', $webhook->tenantId)
            ->where('status', 'PROCESSING')
            ->where('gateway_provider', $webhook->providerName())
            ->where('gateway_transaction_id', $webhook->paymentReference())
            ->exists();
    }

    private function handleStripeNonSuccessfulEvent(PaymentWebhook $webhook): void
    {
        if ($webhook->eventType !== 'invoice.payment_failed') {
            return;
        }

        DB::transaction(function () use ($webhook): void {
            $intent = $this->findLocalIntentForWebhook($webhook);

            if ($intent === null) {
                $this->logger->warning('Stripe non-success webhook ignored: no matching local subscription intent.', [
                    'tenant_id' => $webhook->tenantId,
                    'provider_id' => $webhook->providerId,
                    'subscription_intent_id' => $webhook->subscriptionIntentId(),
                ]);

                return;
            }

            if (strtolower((string) $intent->gateway_provider) !== strtolower($webhook->providerName())) {
                $this->logger->warning('Stripe non-success webhook ignored: local intent provider mismatch.', [
                    'tenant_id' => $webhook->tenantId,
                    'intent_id' => (string) $intent->id,
                    'gateway_provider' => (string) $intent->gateway_provider,
                    'webhook_provider' => $webhook->providerName(),
                ]);

                return;
            }

            $intentState = CanonicalPaymentStateMapper::fromSubscriptionIntent((string) $intent->status);
            if (! $intentState->isInProgress()) {
                $this->logger->info('Stripe non-success webhook ignored: local subscription intent is already terminal.', [
                    'tenant_id' => $webhook->tenantId,
                    'intent_id' => (string) $intent->id,
                    'status' => (string) $intent->status,
                ]);

                return;
            }

            $failed = DB::table('subscription_intents')
                ->where('id', (string) $intent->id)
                ->whereIn('status', ['PENDING', 'PROCESSING'])
                ->update([
                    'status' => 'FAILED',
                    'error_reason' => 'invoice.payment_failed',
                    'gateway_transaction_id' => $webhook->paymentReference(),
                    'updated_at' => now(),
                ]);

            if ($failed !== 1) {
                return;
            }

            DB::table('tenants')
                ->where('id', $webhook->tenantId)
                ->update([
                    'subscription_status' => 'past_due',
                    'updated_at' => now(),
                ]);
        }, 3);
    }

    private function markLocalIntentCompleted(PaymentWebhook $webhook): bool
    {
        $intentId = $webhook->subscriptionIntentId();
        $clientKey = $webhook->clientIdempotencyKey();

        if ($intentId === null && $clientKey === null) {
            return false;
        }

        $query = DB::table('subscription_intents')
            ->where('tenant_id', $webhook->tenantId);

        if ($intentId !== null) {
            $query->where('id', $intentId);
        } else {
            $query->where('client_idempotency_key', $clientKey);
        }

        return $query->whereIn('status', ['PENDING', 'PROCESSING'])->update([
            'status' => 'COMPLETED',
            'gateway_transaction_id' => $webhook->paymentReference(),
            'updated_at' => now(),
        ]) === 1;
    }

    private function persistStripeTenantReferences(PaymentWebhook $webhook): void
    {
        if (! $webhook->isStripe()) {
            return;
        }

        $customerId = $webhook->providerCustomerId();
        $subscriptionId = $webhook->providerSubscriptionId();

        if ($customerId === null && $subscriptionId === null) {
            return;
        }

        $existing = DB::table('tenant_billing_references')
            ->where('tenant_id', $webhook->tenantId)
            ->where('provider', 'stripe')
            ->first();

        $values = [
            'provider_customer_id' => $customerId ?? $existing?->provider_customer_id,
            'provider_subscription_id' => $subscriptionId ?? $existing?->provider_subscription_id,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('tenant_billing_references')->insert([
                'tenant_id' => $webhook->tenantId,
                'provider' => 'stripe',
                'provider_customer_id' => $customerId,
                'provider_subscription_id' => $subscriptionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('tenant_billing_references')
            ->where('id', $existing->id)
            ->update($values);
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
