<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\ReconcilePendingSubscriptionsCommand;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class ReconcilePendingSubscriptionsHandler
{
    public function __construct(
        private SubscriptionGatewayInterface $gateway,
        private LoggerInterface $logger,
        private SubscriptionIntentRepositoryInterface $intentRepository,
        private ClockInterface $clock,
    ) {}

    public function handle(ReconcilePendingSubscriptionsCommand $command): void
    {
        // Metric: Strict 1 hour threshold
        $threshold = $this->clock->now()->modify('-1 hour')->format('Y-m-d H:i:s');

        $intents = $this->intentRepository->findPendingOlderThan($command->tenantId, $threshold);

        foreach ($intents as $intent) {
            try {
                $status = $this->gateway->verifyStatus($intent->client_idempotency_key);

                if ($status === 'PAID') {
                    $this->intentRepository->updateStatus($intent->id, [
                        'status' => 'COMPLETED',
                        'notes' => 'Auto-reconciled by ReconcilePendingSubscriptionsHandler',
                    ]);

                    $this->logger->info("Reconciled PENDING intent to PAID", ['intent_id' => $intent->id]);
                } elseif ($status === 'FAILED') {
                    $this->intentRepository->updateStatus($intent->id, [
                        'status' => 'FAILED',
                        'notes' => 'Auto-reconciled by ReconcilePendingSubscriptionsHandler',
                    ]);

                    $this->logger->info("Reconciled PENDING intent to FAILED", ['intent_id' => $intent->id]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to reconcile subscription intent', [
                    'intent_id' => $intent->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
