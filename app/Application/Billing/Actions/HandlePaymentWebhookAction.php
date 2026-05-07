<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\Payment\PaymentWebhookHandlerInterface;
use App\Domain\Billing\Repositories\WebhookLogRepositoryInterface;
use App\Domain\Billing\Events\PaymentWebhookReceived;
use App\Domain\ValueObjects\Payment\PaymentWebhook;

/**
 * HandlePaymentWebhookAction
 * * Orchestrates the processing of payment webhooks via strict Command DTO.
 */
final readonly class HandlePaymentWebhookAction implements PaymentWebhookHandlerInterface
{
    public function __construct(
        private EventBusInterface $eventBus,
        private LoggerInterface $logger,
        private WebhookLogRepositoryInterface $webhookLogs,
    ) {}

    public function handle(PaymentWebhook $webhook): array
    {
        $this->logger->info('Processing Webhook via Handler', [
            'tenant_id' => $webhook->tenantId,
            'event' => $webhook->eventType,
            'provider' => $webhook->providerName(),
            'provider_event_id' => $webhook->providerId,
        ]);

        $firstSeen = $this->webhookLogs->recordFirstSeen(
            $webhook->providerName(),
            $webhook->providerId,
            $webhook->eventType,
            $webhook->payload,
        );

        if (! $firstSeen) {
            $this->logger->info('Payment webhook replay ignored before side effects', [
                'tenant_id' => $webhook->tenantId,
                'event' => $webhook->eventType,
                'provider' => $webhook->providerName(),
                'provider_event_id' => $webhook->providerId,
            ]);

            return [
                'status' => 'ignored',
                'tenant_id' => $webhook->tenantId,
                'processed' => false,
                'reason' => 'duplicate_event',
                'timestamp' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ];
        }

        // 1. Risk Mitigation: Dispatch Event to Ledger/System
        // This ensures the transaction is recorded asynchronously.
        $this->eventBus->dispatch(new PaymentWebhookReceived($webhook));

        // 2. Acknowledge Receipt
        return [
            'status' => 'acknowledged',
            'tenant_id' => $webhook->tenantId,
            'processed' => true,
            'timestamp' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
        ];
    }
}
