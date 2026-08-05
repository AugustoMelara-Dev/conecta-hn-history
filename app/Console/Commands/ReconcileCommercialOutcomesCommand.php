<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Analytics\TrustedCommercialOutcomeProjector;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Models\Appointment;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReconcileCommercialOutcomesCommand extends Command
{
    protected $signature = 'analytics:reconcile-commercial-outcomes
        {--tenant= : Restrict reconciliation to one tenant UUID}
        {--dry-run : Report missing projections without writing}';

    protected $description = 'Detect and repair missing or drifted server-authoritative commercial outcome projections';

    public function handle(TrustedCommercialOutcomeProjector $projector): int
    {
        $option = $this->option('tenant');
        $tenantId = is_string($option) && $option !== '' ? $option : null;
        $dryRun = (bool) $this->option('dry-run');
        $scanned = 0;
        $missing = 0;
        $drifted = 0;
        $repaired = 0;

        Order::query()
            // JOB_GLOBAL_POLL: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
            ->withoutGlobalScopes()
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->where(function (Builder $query): void {
                $query->whereIn('payment_status', ['paid', 'refunded'])
                    ->orWhereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED]);
            })
            ->chunkById(200, function ($chunk) use ($projector, $dryRun, &$scanned, &$missing, &$drifted, &$repaired): void {
                foreach ($chunk as $order) {
                    $scanned++;
                    $expected = $this->expectedOrderOutcomes($order);
                    [$missingForAggregate, $driftForAggregate] = $this->discrepancyCounts(
                        (string) $order->tenant_id,
                        'order',
                        (string) $order->getKey(),
                        $this->expectedOrderRows($order, $expected),
                    );
                    $missing += $missingForAggregate;
                    $drifted += $driftForAggregate;

                    if (! $dryRun && ($missingForAggregate + $driftForAggregate) > 0) {
                        $projector->projectOrder((string) $order->tenant_id, (int) $order->getKey());
                        $repaired += $missingForAggregate + $driftForAggregate;
                    }
                }
            });

        Appointment::query()
            // JOB_GLOBAL_POLL: reviewed scope bypass; authority is constrained by the adjacent tenant/id predicate.
            ->withoutGlobalScopes()
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->whereIn('status', [
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELLED,
            ])
            ->chunkById(200, function ($chunk) use ($projector, $dryRun, &$scanned, &$missing, &$drifted, &$repaired): void {
                foreach ($chunk as $appointment) {
                    $scanned++;
                    $existingTerminal = DB::table('verified_commercial_outcomes')
                        ->where('tenant_id', (string) $appointment->tenant_id)
                        ->where('aggregate_type', 'appointment')
                        ->where('aggregate_id', (string) $appointment->getKey())
                        ->whereIn('outcome', [
                            CommercialOutcome::AppointmentCompleted->value,
                            CommercialOutcome::AppointmentCancelled->value,
                        ])
                        ->first();
                    $outcome = $existingTerminal !== null
                        ? CommercialOutcome::from((string) $existingTerminal->outcome)
                        : match ($appointment->status) {
                            Appointment::STATUS_CONFIRMED => CommercialOutcome::AppointmentConfirmed,
                            Appointment::STATUS_COMPLETED => CommercialOutcome::AppointmentCompleted,
                            Appointment::STATUS_CANCELLED => CommercialOutcome::AppointmentCancelled,
                            default => null,
                        };

                    if ($outcome === null) {
                        continue;
                    }

                    [$missingForAggregate, $driftForAggregate] = $this->discrepancyCounts(
                        (string) $appointment->tenant_id,
                        'appointment',
                        (string) $appointment->getKey(),
                        [[
                            'outcome' => $outcome,
                            'amount_minor' => $existingTerminal === null ? 0 : (int) $existingTerminal->amount_minor,
                            'currency' => $existingTerminal?->currency === null ? null : (string) $existingTerminal->currency,
                            'source_occurred_at' => $existingTerminal === null
                                ? $this->dateOrFallback(
                                    $appointment->getAttribute('updated_at'),
                                    $appointment->getAttribute('updated_at'),
                                )
                                : Carbon::parse((string) $existingTerminal->source_occurred_at),
                        ]],
                    );
                    $missing += $missingForAggregate;
                    $drifted += $driftForAggregate;

                    if (! $dryRun && ($missingForAggregate + $driftForAggregate) > 0) {
                        $projector->projectAppointment((string) $appointment->tenant_id, (int) $appointment->getKey());
                        $repaired += $missingForAggregate + $driftForAggregate;
                    }
                }
            });

        $this->line(json_encode([
            'dry_run' => $dryRun,
            'tenant_id' => $tenantId,
            'scanned' => $scanned,
            'missing' => $missing,
            'drifted' => $drifted,
            'repaired' => $repaired,
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /** @return list<CommercialOutcome> */
    private function expectedOrderOutcomes(Order $order): array
    {
        $outcomes = [];
        $hasTerminalRefund = DB::table('verified_commercial_outcomes')
            ->where('tenant_id', (string) $order->tenant_id)
            ->where('aggregate_type', 'order')
            ->where('aggregate_id', (string) $order->getKey())
            ->where('outcome', CommercialOutcome::OrderRefunded->value)
            ->exists();

        if ($hasTerminalRefund) {
            $outcomes[] = CommercialOutcome::OrderPaid;
            $outcomes[] = CommercialOutcome::OrderRefunded;
        }

        $paymentStatus = (string) $order->getAttribute('payment_status');

        if (! $hasTerminalRefund && in_array($paymentStatus, ['paid', 'refunded'], true)) {
            $outcomes[] = CommercialOutcome::OrderPaid;
        }
        if (! $hasTerminalRefund && $paymentStatus === 'refunded' && $order->getAttribute('refunded_at') !== null) {
            $outcomes[] = CommercialOutcome::OrderRefunded;
        }
        $existingTerminal = DB::table('verified_commercial_outcomes')
            ->where('tenant_id', (string) $order->tenant_id)
            ->where('aggregate_type', 'order')
            ->where('aggregate_id', (string) $order->getKey())
            ->whereIn('outcome', [
                CommercialOutcome::OrderDelivered->value,
                CommercialOutcome::OrderCancelled->value,
            ])
            ->value('outcome');

        if (is_string($existingTerminal)) {
            $outcomes[] = CommercialOutcome::from($existingTerminal);
        } elseif ($order->status === Order::STATUS_DELIVERED) {
            $outcomes[] = CommercialOutcome::OrderDelivered;
        } elseif ($order->status === Order::STATUS_CANCELLED) {
            $outcomes[] = CommercialOutcome::OrderCancelled;
        }

        return $outcomes;
    }

    /**
     * @param  list<CommercialOutcome>  $outcomes
     * @return list<array{outcome: CommercialOutcome, amount_minor: int, currency: ?string, source_occurred_at: CarbonInterface}>
     */
    private function expectedOrderRows(Order $order, array $outcomes): array
    {
        $totalMinor = $this->decimalToMinor((string) $order->getRawOriginal('total'));
        $currency = strtoupper((string) $order->getRawOriginal('currency'));
        $terminalRefund = DB::table('verified_commercial_outcomes')
            ->where('tenant_id', (string) $order->tenant_id)
            ->where('aggregate_type', 'order')
            ->where('aggregate_id', (string) $order->getKey())
            ->where('outcome', CommercialOutcome::OrderRefunded->value)
            ->first();

        return array_map(function (CommercialOutcome $outcome) use ($order, $totalMinor, $currency, $terminalRefund): array {
            if ($terminalRefund !== null && $outcome->carriesRevenue()) {
                $paid = DB::table('verified_commercial_outcomes')
                    ->where('tenant_id', (string) $order->tenant_id)
                    ->where('aggregate_type', 'order')
                    ->where('aggregate_id', (string) $order->getKey())
                    ->where('outcome', CommercialOutcome::OrderPaid->value)
                    ->first();

                return [
                    'outcome' => $outcome,
                    'amount_minor' => $outcome === CommercialOutcome::OrderRefunded
                        ? (int) $terminalRefund->amount_minor
                        : -(int) $terminalRefund->amount_minor,
                    'currency' => $terminalRefund->currency === null ? null : (string) $terminalRefund->currency,
                    'source_occurred_at' => Carbon::parse((string) (
                        $outcome === CommercialOutcome::OrderPaid && $paid !== null
                            ? $paid->source_occurred_at
                            : $terminalRefund->source_occurred_at
                    )),
                ];
            }

            return match ($outcome) {
                CommercialOutcome::OrderPaid => [
                    'outcome' => $outcome,
                    'amount_minor' => $totalMinor,
                    'currency' => $currency,
                    'source_occurred_at' => $this->dateOrFallback(
                        $order->getAttribute('paid_at'),
                        $order->getAttribute('updated_at'),
                    ),
                ],
                CommercialOutcome::OrderRefunded => [
                    'outcome' => $outcome,
                    'amount_minor' => -$totalMinor,
                    'currency' => $currency,
                    'source_occurred_at' => $this->dateOrFallback(
                        $order->getAttribute('refunded_at'),
                        $order->getAttribute('updated_at'),
                    ),
                ],
                CommercialOutcome::OrderDelivered => [
                    'outcome' => $outcome,
                    'amount_minor' => 0,
                    'currency' => null,
                    'source_occurred_at' => $this->dateOrFallback(
                        $order->getAttribute('delivered_at'),
                        $order->getAttribute('updated_at'),
                    ),
                ],
                CommercialOutcome::OrderCancelled => [
                    'outcome' => $outcome,
                    'amount_minor' => 0,
                    'currency' => null,
                    'source_occurred_at' => $this->dateOrFallback(
                        $order->getAttribute('cancelled_at'),
                        $order->getAttribute('updated_at'),
                    ),
                ],
                default => throw new \LogicException('Unexpected order outcome during reconciliation.'),
            };
        }, $outcomes);
    }

    /**
     * @param  list<array{outcome: CommercialOutcome, amount_minor: int, currency: ?string, source_occurred_at: CarbonInterface}>  $expectedRows
     * @return array{int, int} Missing and drifted row counts.
     */
    private function discrepancyCounts(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        array $expectedRows,
    ): array {
        $missing = 0;
        $drifted = 0;

        foreach ($expectedRows as $expected) {
            $row = DB::table('verified_commercial_outcomes')
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', $aggregateType)
                ->where('aggregate_id', $aggregateId)
                ->where('outcome', $expected['outcome']->value)
                ->first();

            if ($row === null) {
                $missing++;

                continue;
            }

            $currency = $row->currency === null ? null : (string) $row->currency;
            $occurredAt = Carbon::parse((string) $row->source_occurred_at);

            if ((int) $row->amount_minor !== $expected['amount_minor']
                || $currency !== $expected['currency']
                || ! $occurredAt->equalTo($expected['source_occurred_at'])) {
                $drifted++;
            }
        }

        return [$missing, $drifted];
    }

    private function decimalToMinor(string $decimal): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $decimal, $matches) !== 1) {
            throw new \LogicException('Authoritative order total is not a valid decimal amount.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function dateOrFallback(mixed $value, mixed $fallback): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return $fallback instanceof CarbonInterface ? $fallback : now();
    }
}
