<?php

declare(strict_types=1);

namespace App\Application\Billing\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Billing\Events\PaymentWebhookReceived;
use App\Domain\Shared\Services\TenantContext;
use App\Models\SubscriptionPayment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class ProcessPaymentWebhookReceived
{
    public function __construct(
        private LoggerInterface $logger,
        private TenantContext $tenantContext,
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

            $payment = DB::transaction(function () use ($webhook) {
                $payment = $this->findExistingPayment($webhook->tenantId, $webhook->providerId, $webhook->paymentReference(), $webhook->providerName());

                if ($payment === null) {
                    $payment = $this->createPayment($webhook);
                }

                return $payment;
            }, 3);

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

                $this->logger->info('Payment webhook materialized and approved subscription payment.', [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                    'reference_number' => $payment->reference_number,
                    'idempotency_key' => $payment->idempotency_key,
                ]);

                return;
            }

            if ($payment->isApproved()) {
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

    private function createPayment(\App\Domain\ValueObjects\Payment\PaymentWebhook $webhook): SubscriptionPayment
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
                })->orWhere(function ($inner) use ($bank, $referenceNumber): void {
                    $inner->where('bank', $bank)
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
}
