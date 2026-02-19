<?php

declare(strict_types=1);

namespace App\Application\Billing\Jobs;

use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Entities\SubscriptionIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;

/**
 * ReconcilePendingSubscriptionsJob - Self-Healing Mechanism
 *
 * Scans for "Zombie" SubscriptionIntents (PENDING > 1h) and reconciles
 * them with the remote Payment Gateway.
 */
class ReconcilePendingSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(
        SubscriptionGatewayInterface $gateway,
        LoggerInterface $logger,
        ConnectionInterface $connection,
    ): void {
        // Metric: Strict 1 hour threshold
        $threshold = now()->subHour()->toDateTimeString();

        $intents = $connection->table('subscription_intents')
            ->where('status', 'PENDING')
            ->where('created_at', '<', $threshold)
            ->get();

        foreach ($intents as $intent) {
            try {
                $status = $gateway->verifyStatus($intent->client_idempotency_key);

                if ($status === 'PAID') {
                    $connection->table('subscription_intents')
                        ->where('id', $intent->id)
                        ->update([
                            'status' => 'COMPLETED',
                            'updated_at' => now(),
                            'notes' => 'Auto-reconciled by ReconcilePendingSubscriptionsJob'
                        ]);
                    
                    $logger->info("Reconciled PENDING intent to PAID", ['intent_id' => $intent->id]);
                } elseif ($status === 'FAILED') {
                    $connection->table('subscription_intents')
                        ->where('id', $intent->id)
                        ->update([
                            'status' => 'FAILED',
                            'updated_at' => now(),
                            'notes' => 'Auto-reconciled by ReconcilePendingSubscriptionsJob'
                        ]);
                        
                    $logger->info("Reconciled PENDING intent to FAILED", ['intent_id' => $intent->id]);
                }
            } catch (\Throwable $e) {
                report($e); // Fail-safe
            }
        }
    }
}
