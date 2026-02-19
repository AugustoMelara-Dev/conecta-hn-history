<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\Payment\PaymentWebhookHandlerInterface;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use App\Events\Payment\PaymentWebhookReceived;

/**
 * HandlePaymentWebhookAction
 * * Orchestrates the processing of payment webhooks via strict Command DTO.
 */
final readonly class HandlePaymentWebhookAction implements PaymentWebhookHandlerInterface
{
    public function __construct(
        private EventBusInterface $eventBus,
        private LoggerInterface $logger,
    ) {}

    public function handle(PaymentWebhook $webhook): array
    {
        $this->logger->info("Processing Webhook via Handler", [
            'tenant_id' => $webhook->tenantId,
            'event' => $webhook->eventType
        ]);

        // 1. Risk Mitigation: Dispatch Event to Ledger/System
        // This ensures the transaction is recorded asynchronously.
        $this->eventBus->dispatch(new PaymentWebhookReceived($webhook));

        // 2. Acknowledge Receipt
        return [
            'status' => 'acknowledged',
            'tenant_id' => $webhook->tenantId,
            'processed' => true,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
