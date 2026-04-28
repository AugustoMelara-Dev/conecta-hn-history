<?php

declare(strict_types=1);

namespace App\Application\Billing\Services;

use App\Domain\Billing\DTOs\ChargeResult;
use App\Domain\Billing\Gateways\PaymentGatewayInterface;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final readonly class RefundSubscriptionPaymentService
{
    public function __construct(
        private PaymentGatewayInterface $paymentGateway,
    ) {}

    public function refund(
        string $paymentId,
        ?int $amountInCents = null,
        ?string $reason = null,
    ): ChargeResult {
        return DB::transaction(function () use ($paymentId, $amountInCents, $reason): ChargeResult {
            /** @var SubscriptionPayment|null $payment */
            $payment = SubscriptionPayment::withoutGlobalScopes()
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment instanceof SubscriptionPayment) {
                throw new InvalidArgumentException('Subscription payment not found.');
            }

            if (! $payment->isApproved()) {
                throw new InvalidArgumentException('Only approved subscription payments can be refunded.');
            }

            if ($payment->refunded_at !== null && is_string($payment->refund_transaction_id)) {
                return ChargeResult::success($payment->refund_transaction_id);
            }

            $refund = $this->paymentGateway->refund(
                transactionId: (string) $payment->reference_number,
                amountInCents: $amountInCents,
            );

            if (! $refund->success || $refund->transactionId === null) {
                return $refund;
            }

            $payment->forceFill([
                'refund_transaction_id' => $refund->transactionId,
                'refund_amount' => $amountInCents === null ? $payment->amount : number_format($amountInCents / 100, 2, '.', ''),
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ])->save();

            Log::info('Subscription payment refunded', [
                'payment_id' => (string) $payment->getKey(),
                'tenant_id' => (string) $payment->tenant_id,
                'refund_transaction_id' => $refund->transactionId,
                'amount_cents' => $amountInCents,
                'reason' => $reason,
            ]);

            return $refund;
        }, 3);
    }
}
