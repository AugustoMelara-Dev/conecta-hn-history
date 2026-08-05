<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\AI\AiExecutionStore;
use App\Infrastructure\AI\BudgetEnforcer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReconcileStaleAiReservationsCommand extends Command
{
    protected $signature = 'ai:reconcile-reservations {--older-than=60 : Age in minutes} {--limit=500 : Maximum reservations per run}';

    protected $description = 'Settle completed AI executions or refund abandoned stale reservations idempotently.';

    public function handle(BudgetEnforcer $budgetEnforcer, AiExecutionStore $executions): int
    {
        $olderThan = max(15, (int) $this->option('older-than'));
        $limit = min(5000, max(1, (int) $this->option('limit')));
        $settled = 0;
        $refunded = 0;
        $skipped = 0;

        // Claims that never reached the atomic reserve+attach boundary are safe
        // to close after their lease; no provider call could have been funded.
        DB::table('ai_executions')
            ->where('status', 'processing')
            ->whereNull('reservation_id')
            ->where('processing_expires_at', '<=', now()->utc())
            ->update([
                'status' => 'failed',
                'failure_code' => 'claim_lease_expired',
                'failed_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);

        $reservations = DB::table('ai_credit_reservations')
            ->where('denomination', 'commercial_credits')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->utc()->subMinutes($olderThan))
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'tenant_id', 'estimated_cost']);

        foreach ($reservations as $reservation) {
            try {
                $execution = DB::table('ai_executions')
                    ->where('reservation_id', (string) $reservation->id)
                    ->first(['correlation_id', 'status', 'charged_cost', 'charged_credits', 'credit_pricing_version', 'credits_per_provider_usd', 'credit_minimum_charge', 'provider_cost', 'provider', 'model', 'failure_code']);

                if ($execution !== null && $execution->status === 'accounting_pending' && $execution->charged_credits !== null) {
                    DB::transaction(function () use ($budgetEnforcer, $executions, $reservation, $execution): void {
                        $budgetEnforcer->settle(
                            tenantId: (string) $reservation->tenant_id,
                            estimatedCost: (float) $reservation->estimated_cost,
                            actualCost: (float) $execution->charged_credits,
                            reservationId: (string) $reservation->id,
                            usageMetadata: [
                                'provider' => (string) $execution->provider,
                                'model' => (string) $execution->model,
                                'correlation_id' => (string) $execution->correlation_id,
                                'provider_cost_usd' => (float) $execution->provider_cost,
                                'charged_cost_usd' => (float) $execution->charged_cost,
                                'charged_credits' => (float) $execution->charged_credits,
                                'credit_pricing_version' => (string) $execution->credit_pricing_version,
                                'credits_per_provider_usd' => (float) $execution->credits_per_provider_usd,
                                'credit_minimum_charge' => (float) $execution->credit_minimum_charge,
                                'reconciled' => true,
                            ],
                        );
                        if ($execution->failure_code !== null) {
                            $executions->reconcileFail(
                                (string) $execution->correlation_id,
                                (string) $execution->failure_code,
                            );
                        } else {
                            $executions->reconcileCompleteStaged((string) $execution->correlation_id);
                        }
                    }, 3);
                    $settled++;

                    continue;
                }

                $budgetEnforcer->refund(
                    tenantId: (string) $reservation->tenant_id,
                    estimatedCost: (float) $reservation->estimated_cost,
                    reservationId: (string) $reservation->id,
                    failureReason: "stale_reservation_reconciled_after_{$olderThan}_minutes",
                );

                if ($execution !== null) {
                    $executions->reconcileFail((string) $execution->correlation_id, 'stale_execution_reconciled');
                }
                $refunded++;
            } catch (Throwable $exception) {
                report($exception);
                $skipped++;
            }
        }

        $this->info("AI reservations reconciled: {$settled} settled; {$refunded} refunded; {$skipped} skipped.");

        return $skipped === 0 ? self::SUCCESS : self::FAILURE;
    }
}
