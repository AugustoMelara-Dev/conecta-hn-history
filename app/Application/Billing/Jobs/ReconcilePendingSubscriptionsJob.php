<?php

declare(strict_types=1);

namespace App\Application\Billing\Jobs;

use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Entities\SubscriptionIntent;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * ReconcilePendingSubscriptionsJob - Self-Healing Mechanism
 *
 * Scans for "Zombie" SubscriptionIntents (PENDING > 1h) and reconciles
 * them with the remote Payment Gateway.
 *
 * REFACTORED: Replaced ConnectionInterface with SubscriptionIntentRepositoryInterface.
 */
final readonly class ReconcilePendingSubscriptionsJob
{
    public function __construct(
        public readonly string $tenantId,
    ) {}

    public function handle(
        SubscriptionGatewayInterface $gateway,
        LoggerInterface $logger,
        SubscriptionIntentRepositoryInterface $intentRepository,
        ClockInterface $clock,
    ): void {
        // Metric: Strict 1 hour threshold
        $threshold = $clock->now()->modify('-1 hour')->format('Y-m-d H:i:s');

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
                $logger->error('Failed to reconcile subscription intent', [
                    'intent_id' => $intent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
