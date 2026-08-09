<?php

declare(strict_types=1);

namespace App\Application\Payments\Actions;

// @withoutGlobalScopes-safety WEBHOOK_LOCAL_INTENT_MATCH: lookup is constrained by tenant, order and checkout idempotency metadata.

use App\Models\Order;
use Illuminate\Support\Facades\DB;

final readonly class SettleOrderPaymentAction
{
    /** @return array{order_id:int,refund_required:bool} */
    public function execute(
        string $tenantId,
        int $orderId,
        string $orderIdempotencyKey,
        int $amountInCents,
        string $currency,
        string $paymentReference,
    ): array {
        return DB::transaction(function () use (
            $tenantId,
            $orderId,
            $orderIdempotencyKey,
            $amountInCents,
            $currency,
            $paymentReference,
        ): array {
            $order = Order::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals((string) $order->idempotency_key, $orderIdempotencyKey)) {
                throw new \InvalidArgumentException('Order payment metadata does not match the local checkout.');
            }
            if ((int) round(((float) $order->total) * 100) !== $amountInCents
                || ! hash_equals(strtoupper((string) $order->currency), strtoupper($currency))) {
                throw new \InvalidArgumentException('Order payment amount or currency does not match checkout.');
            }

            $paymentStatus = (string) $order->payment_status;
            if ($paymentStatus === 'paid'
                && hash_equals((string) $order->payment_transaction_id, $paymentReference)) {
                return ['order_id' => (int) $order->getKey(), 'refund_required' => false];
            }
            if (! in_array($paymentStatus, ['pending', 'paid'], true)) {
                throw new \LogicException('Order payment state cannot accept settlement.');
            }
            if ($paymentStatus === 'paid') {
                throw new \LogicException('Order was already paid by another provider transaction.');
            }

            $cancelled = $order->status === Order::STATUS_CANCELLED;
            $order->forceFill([
                'payment_status' => $cancelled ? 'refund_pending' : 'paid',
                'payment_reference' => $paymentReference,
                'payment_transaction_id' => $paymentReference,
                'payment_confirmed_at' => now(),
                'paid_at' => now(),
                'status' => $cancelled ? Order::STATUS_CANCELLED : Order::STATUS_CONFIRMED,
                'confirmed_at' => $cancelled ? $order->confirmed_at : ($order->confirmed_at ?? now()),
            ])->save();

            return ['order_id' => (int) $order->getKey(), 'refund_required' => $cancelled];
        }, 3);
    }
}
