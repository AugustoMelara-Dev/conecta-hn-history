<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\Billing\Enums\CanonicalPaymentState;
use App\Domain\Billing\Mappers\CanonicalPaymentStateMapper;
use App\Models\Appointment;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use UnexpectedValueException;

final class TrustedCommercialOutcomeProjector
{
    /** @return list<CommercialOutcome> */
    public function projectOrder(string $tenantId, int $orderId): array
    {
        return DB::transaction(function () use ($tenantId, $orderId): array {
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

            $projected = [];
            $paymentState = $this->orderPaymentState($order);
            $paidAt = $order->getAttribute('paid_at');
            $refundedAt = $order->getAttribute('refunded_at');
            $terminalRefund = $this->outcomeRow(
                $tenantId,
                'order',
                (string) $order->getKey(),
                CommercialOutcome::OrderRefunded,
            );

            if ($terminalRefund !== null) {
                $refundMinor = (int) $terminalRefund->amount_minor;
                $refundCurrency = (string) $terminalRefund->currency;
                if ($refundMinor >= 0 || preg_match('/^[A-Z]{3}$/', $refundCurrency) !== 1) {
                    throw new LogicException('Terminal refund projection is corrupt.');
                }

                $existingPaid = $this->outcomeRow(
                    $tenantId,
                    'order',
                    (string) $order->getKey(),
                    CommercialOutcome::OrderPaid,
                );
                $this->upsert(
                    tenantId: $tenantId,
                    aggregateType: 'order',
                    aggregateId: (string) $order->getKey(),
                    outcome: CommercialOutcome::OrderPaid,
                    amountMinor: -$refundMinor,
                    currency: $refundCurrency,
                    occurredAt: Carbon::parse((string) ($existingPaid->source_occurred_at ?? $terminalRefund->source_occurred_at)),
                );
                $projected[] = CommercialOutcome::OrderPaid;
            } elseif ($paymentState === CanonicalPaymentState::Succeeded
                || $paymentState === CanonicalPaymentState::Refunded) {
                $this->upsert(
                    tenantId: $tenantId,
                    aggregateType: 'order',
                    aggregateId: (string) $order->getKey(),
                    outcome: CommercialOutcome::OrderPaid,
                    amountMinor: $this->decimalToMinor((string) $order->getRawOriginal('total')),
                    currency: $this->orderCurrency($order),
                    occurredAt: $this->dateOrFallback($paidAt, $order),
                );
                $projected[] = CommercialOutcome::OrderPaid;
            }

            if ($terminalRefund === null
                && $paymentState === CanonicalPaymentState::Refunded
                && $refundedAt !== null) {
                $this->upsert(
                    tenantId: $tenantId,
                    aggregateType: 'order',
                    aggregateId: (string) $order->getKey(),
                    outcome: CommercialOutcome::OrderRefunded,
                    amountMinor: -$this->decimalToMinor((string) $order->getRawOriginal('total')),
                    currency: $this->orderCurrency($order),
                    occurredAt: $this->dateOrFallback($refundedAt, $order),
                );
                $projected[] = CommercialOutcome::OrderRefunded;
            }

            if ($order->status === Order::STATUS_DELIVERED
                && ! $this->hasOutcome($tenantId, 'order', (string) $order->getKey(), CommercialOutcome::OrderCancelled)) {
                $this->upsertOrderStatus($order, CommercialOutcome::OrderDelivered, 'delivered_at');
                $projected[] = CommercialOutcome::OrderDelivered;
            } elseif ($order->status === Order::STATUS_CANCELLED
                && ! $this->hasOutcome($tenantId, 'order', (string) $order->getKey(), CommercialOutcome::OrderDelivered)) {
                $this->upsertOrderStatus($order, CommercialOutcome::OrderCancelled, 'cancelled_at');
                $projected[] = CommercialOutcome::OrderCancelled;
            }

            return $projected;
        });
    }

    /** @return list<CommercialOutcome> */
    public function projectAppointment(string $tenantId, int $appointmentId): array
    {
        return DB::transaction(function () use ($tenantId, $appointmentId): array {
            $appointment = Appointment::query()
                // INFRA_TENANT_FILTER: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
                ->withoutGlobalScopes()
                ->whereKey($appointmentId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (! $appointment instanceof Appointment) {
                throw new LogicException('Appointment is not owned by the trusted tenant.');
            }

            $outcome = match ($appointment->status) {
                Appointment::STATUS_CONFIRMED => CommercialOutcome::AppointmentConfirmed,
                Appointment::STATUS_COMPLETED => CommercialOutcome::AppointmentCompleted,
                Appointment::STATUS_CANCELLED => CommercialOutcome::AppointmentCancelled,
                default => null,
            };

            if ($outcome === null) {
                return [];
            }

            if ($outcome === CommercialOutcome::AppointmentCompleted
                && $this->hasOutcome($tenantId, 'appointment', (string) $appointment->getKey(), CommercialOutcome::AppointmentCancelled)) {
                return [];
            }
            if ($outcome === CommercialOutcome::AppointmentCancelled
                && $this->hasOutcome($tenantId, 'appointment', (string) $appointment->getKey(), CommercialOutcome::AppointmentCompleted)) {
                return [];
            }

            $this->upsert(
                tenantId: $tenantId,
                aggregateType: 'appointment',
                aggregateId: (string) $appointment->getKey(),
                outcome: $outcome,
                amountMinor: 0,
                currency: null,
                occurredAt: $this->dateOrFallback($appointment->getAttribute('updated_at'), $appointment),
            );

            return [$outcome];
        });
    }

    private function upsertOrderStatus(Order $order, CommercialOutcome $outcome, string $timestamp): void
    {
        $this->upsert(
            tenantId: (string) $order->tenant_id,
            aggregateType: 'order',
            aggregateId: (string) $order->getKey(),
            outcome: $outcome,
            amountMinor: 0,
            currency: null,
            occurredAt: $this->dateOrFallback($order->getAttribute($timestamp), $order),
        );
    }

    private function upsert(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        CommercialOutcome $outcome,
        int $amountMinor,
        ?string $currency,
        CarbonInterface $occurredAt,
    ): void {
        $projectionKey = "{$tenantId}:{$aggregateType}:{$aggregateId}:{$outcome->value}";
        $existing = DB::table('verified_commercial_outcomes')
            ->where('projection_key', $projectionKey)
            ->lockForUpdate()
            ->first();

        if ($existing !== null
            && ((string) $existing->tenant_id !== $tenantId
                || (string) $existing->aggregate_type !== $aggregateType
                || (string) $existing->aggregate_id !== $aggregateId)) {
            throw new LogicException('Commercial outcome projection key ownership mismatch.');
        }

        DB::table('verified_commercial_outcomes')->upsert(
            [[
                'projection_key' => $projectionKey,
                'tenant_id' => $tenantId,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'outcome' => $outcome->value,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'source_occurred_at' => $occurredAt,
                'source_version' => 1,
                'metadata' => json_encode(['authority' => 'server_aggregate'], JSON_THROW_ON_ERROR),
                'created_at' => $existing->created_at ?? now(),
                'updated_at' => now(),
            ]],
            ['projection_key'],
            [
                'tenant_id',
                'aggregate_type',
                'aggregate_id',
                'outcome',
                'amount_minor',
                'currency',
                'source_occurred_at',
                'source_version',
                'metadata',
                'updated_at',
            ],
        );
    }

    private function orderPaymentState(Order $order): CanonicalPaymentState
    {
        try {
            return CanonicalPaymentStateMapper::fromOrderPaymentStatus(
                (string) $order->getAttribute('payment_status'),
            );
        } catch (UnexpectedValueException) {
            return CanonicalPaymentState::Pending;
        }
    }

    private function hasOutcome(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        CommercialOutcome $outcome,
    ): bool {
        return DB::table('verified_commercial_outcomes')
            ->where('tenant_id', $tenantId)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->where('outcome', $outcome->value)
            ->exists();
    }

    private function outcomeRow(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        CommercialOutcome $outcome,
    ): ?object {
        return DB::table('verified_commercial_outcomes')
            ->where('tenant_id', $tenantId)
            ->where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->where('outcome', $outcome->value)
            ->first();
    }

    private function decimalToMinor(string $decimal): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $decimal, $matches) !== 1) {
            throw new LogicException('Authoritative order total is not a valid decimal amount.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function orderCurrency(Order $order): string
    {
        $currency = strtoupper((string) $order->getRawOriginal('currency'));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new LogicException('Authoritative order currency is invalid.');
        }

        return $currency;
    }

    private function dateOrFallback(mixed $value, Model $model): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        $updatedAt = $model->getAttribute('updated_at');

        return $updatedAt instanceof CarbonInterface ? $updatedAt : now();
    }
}
