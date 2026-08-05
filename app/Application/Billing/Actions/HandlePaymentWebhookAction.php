<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\Payment\PaymentWebhookHandlerInterface;
use App\Domain\Billing\Events\PaymentWebhookReceived;
use App\Domain\Billing\Repositories\WebhookLogRepositoryInterface;
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

        $claimed = $this->webhookLogs->claim(
            $webhook->providerName(),
            $webhook->providerId,
            $webhook->eventType,
            $webhook->payload,
        );

        if ($claimed === null) {
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

        try {
            // Dispatch is synchronous at this boundary: the durable ledger reaches
            // processed only after its side effects have completed.
            $this->eventBus->dispatch(new PaymentWebhookReceived($webhook));
            if (! $this->webhookLogs->markProcessed($claimed)) {
                throw new \RuntimeException('Webhook processing lease was lost before it could be marked processed.');
            }
        } catch (\Throwable $error) {
            try {
                $this->webhookLogs->markFailed($claimed, $error);
            } catch (\Throwable $markFailedError) {
                try {
                    $this->logger->error('Payment webhook failed and its durable failure marker could not be written.', [
                        'provider' => $webhook->providerName(),
                        'provider_event_id' => $webhook->providerId,
                        'mark_failed_exception' => $markFailedError::class,
                    ]);
                } catch (\Throwable) {
                    // The original dispatch error remains authoritative.
                }
            }

            throw $error;
        }

        // 2. Acknowledge Receipt
        return [
            'status' => 'acknowledged',
            'tenant_id' => $webhook->tenantId,
            'processed' => true,
            'timestamp' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
        ];
    }
}
