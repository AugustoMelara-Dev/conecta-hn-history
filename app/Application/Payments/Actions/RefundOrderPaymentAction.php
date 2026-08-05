<?php

declare(strict_types=1);

namespace App\Application\Payments\Actions;

use App\Domain\Payments\Gateways\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

final readonly class RefundOrderPaymentAction
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    public function execute(string $tenantId, int $orderId): string
    {
        $claim = DB::transaction(function () use ($tenantId, $orderId): array {
            $order = Order::query()
                // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
                ->withoutGlobalScopes()
                ->whereKey($orderId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                throw new LogicException('Order is not owned by the trusted tenant.');
            }

            $paymentStatus = (string) $order->getAttribute('payment_status');
            if ($paymentStatus === 'refunded') {
                $refundId = (string) $order->getAttribute('refund_transaction_id');

                if ($refundId === '') {
                    throw new LogicException('Refunded order is missing its gateway reference.');
                }

                return ['already_refunded' => true, 'refund_id' => $refundId];
            }

            $transactionId = (string) $order->getAttribute('payment_transaction_id');
            if (! in_array($paymentStatus, ['paid', 'refund_pending'], true) || $transactionId === '') {
                throw new LogicException('Only a gateway-confirmed paid order can be refunded.');
            }

            $idempotencyKey = (string) $order->getAttribute('refund_idempotency_key');
            if ($idempotencyKey === '') {
                $idempotencyKey = 'order-refund-'.hash('sha256', "{$tenantId}:{$orderId}");
            }

            $order->forceFill([
                'payment_status' => 'refund_pending',
                'refund_idempotency_key' => $idempotencyKey,
                'refund_attempted_at' => now(),
            ])->save();

            return [
                'already_refunded' => false,
                'payment_transaction_id' => $transactionId,
                'idempotency_key' => $idempotencyKey,
            ];
        }, 3);

        if ($claim['already_refunded'] === true) {
            return (string) $claim['refund_id'];
        }

        $result = $this->gateway->refund(
            (string) $claim['payment_transaction_id'],
            idempotencyKey: (string) $claim['idempotency_key'],
        );
        if (! $result->success || $result->transactionId === null || $result->transactionId === '') {
            throw new RuntimeException($result->errorMessage ?? 'Payment gateway rejected the refund.');
        }

        DB::transaction(function () use ($tenantId, $orderId, $result): void {
            $order = Order::query()
                // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
                ->withoutGlobalScopes()
                ->whereKey($orderId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $paymentStatus = (string) $order->getAttribute('payment_status');
            if ($paymentStatus === 'refunded') {
                return;
            }
            if ($paymentStatus !== 'refund_pending') {
                throw new LogicException('Order refund state changed before gateway settlement.');
            }

            $order->forceFill([
                'payment_status' => 'refunded',
                'refund_transaction_id' => $result->transactionId,
                'refunded_at' => now(),
            ])->save();
        }, 3);

        return $result->transactionId;
    }
}
