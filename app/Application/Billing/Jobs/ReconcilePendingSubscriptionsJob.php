<?php

declare(strict_types=1);

namespace App\Application\Billing\Jobs;

use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Entities\SubscriptionIntent;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;

/**
 * ReconcilePendingSubscriptionsJob - Self-Healing Mechanism
 *
 * Scans for "Zombie" SubscriptionIntents (PENDING > 1h) and reconciles
 * them with the remote Payment Gateway.
 *
 * REFACTORED: Replaced ConnectionInterface with SubscriptionIntentRepositoryInterface.
 */
final class ReconcilePendingSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly string $tenantId,
    ) {}

    public function handle(
        SubscriptionGatewayInterface $gateway,
        LoggerInterface $logger,
        SubscriptionIntentRepositoryInterface $intentRepository,
    ): void {
        // Metric: Strict 1 hour threshold
        $threshold = now()->subHour()->toDateTimeString();

        $intents = $intentRepository->findPendingOlderThan($this->tenantId, $threshold);

        foreach ($intents as $intent) {
            try {
                $status = $gateway->verifyStatus($intent->client_idempotency_key);

                if ($status === 'PAID') {
                    $intentRepository->updateStatus($intent->id, [
                        'status' => 'COMPLETED',
                        'notes' => 'Auto-reconciled by ReconcilePendingSubscriptionsJob',
                    ]);

                    $logger->info("Reconciled PENDING intent to PAID", ['intent_id' => $intent->id]);
                } elseif ($status === 'FAILED') {
                    $intentRepository->updateStatus($intent->id, [
                        'status' => 'FAILED',
                        'notes' => 'Auto-reconciled by ReconcilePendingSubscriptionsJob',
                    ]);

                    $logger->info("Reconciled PENDING intent to FAILED", ['intent_id' => $intent->id]);
                }
            } catch (\Throwable $e) {
                report($e); // Fail-safe
            }
        }
    }
}
