<?php

declare(strict_types=1);

namespace App\Application\Payments\Handlers;

use App\Application\Payments\Commands\HandlePaymentWebhookCommand;
use App\Application\Payments\Results\PaymentWebhookResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Events\PaymentFailed;
use App\Domain\Billing\Events\PaymentReceived;
use App\Domain\Payment\Repositories\WebhookLogRepositoryInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HandlePaymentWebhookHandler - Gateway for External Payment Webhooks
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDEMPOTENT: Rejects duplicate events via external_event_id check.      ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO User/Subscription logic (translated to domain events)             ║
 * ║  - Responds FAST (< 200ms) - heavy processing in async listeners        ║
 * ║  - Translates provider-specific events to internal domain events        ║
 * ║  - STRICT RETURN: PaymentWebhookResult DTO (no array leak)              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class HandlePaymentWebhookHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly WebhookLogRepositoryInterface $logRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Translate provider-specific event to internal domain event.
     */
    private function translateToDomainEvent(HandlePaymentWebhookCommand $command): ?object
    {
        $transactionId = Str::uuid()->toString();
        $payload = $command->payload;

        return match ($command->eventType) {
            // Stripe events
            'payment_intent.succeeded',
            'charge.succeeded',
            'payment.succeeded' => new PaymentReceived(
                transactionId: $transactionId,
                providerReference: $payload['id'] ?? $command->externalEventId,
                amount: (int) ($payload['amount'] ?? $payload['data']['object']['amount'] ?? 0),
                currency: $payload['currency'] ?? $payload['data']['object']['currency'] ?? 'HNL',
                payerEmail: $payload['receipt_email'] ?? $payload['data']['object']['receipt_email'] ?? null,
                tenantId: $command->tenantId,
            ),

            'payment_intent.payment_failed',
            'charge.failed',
            'invoice.payment_failed',
            'payment.failed' => new PaymentFailed(
                transactionId: $transactionId,
                providerReference: $payload['id'] ?? $command->externalEventId,
                reason: $payload['failure_message']
                    ?? $payload['data']['object']['failure_message']
                    ?? 'Payment failed',
                failureCode: $payload['failure_code']
                    ?? $payload['data']['object']['failure_code']
                    ?? null,
                tenantId: $command->tenantId,
            ),

            // Unhandled events - log but don't fail
            default => null,
        };
    }

    /**
     * Handle incoming payment webhook.
     */
    public function handle(HandlePaymentWebhookCommand $command): PaymentWebhookResult
    {
        // STEP 1: IDEMPOTENCY GUARD
        if ($this->logRepository->hasBeenProcessed($command->provider, $command->externalEventId)) {
            Log::info('Webhook already processed (idempotency)', [
                'provider' => $command->provider,
                'external_event_id' => $command->externalEventId,
            ]);

            return PaymentWebhookResult::duplicate($command->externalEventId);
        }

        // STEP 2: AUDIT LOG (before processing)
        $this->logRepository->log(
            $command->provider,
            $command->externalEventId,
            $command->eventType,
            $command->payload
        );

        // STEP 3: EVENT TRANSLATION
        $domainEvent = $this->translateToDomainEvent($command);

        if ($domainEvent === null) {
            Log::info('Webhook event type not handled', [
                'provider' => $command->provider,
                'event_type' => $command->eventType,
            ]);

            // Ignored events usually don't have transaction ID relevant to us, or we use external ID
            return PaymentWebhookResult::processed(
                transactionId: $command->externalEventId,
                status: 'IGNORED',
                updatedOrder: false
            );
        }

        // STEP 4: DISPATCH TO DOMAIN
        $this->eventDispatcher->dispatch($domainEvent);

        Log::info('Webhook processed successfully', $this->sanitizePayload([
            'provider' => $command->provider,
            'event_type' => $command->eventType,
            'domain_event' => get_class($domainEvent),
        ]));

        // Determine Status based on Event Type
        $internalStatus = $domainEvent instanceof PaymentReceived ? 'PAID' : 'FAILED';

        return PaymentWebhookResult::processed(
            transactionId: $command->externalEventId,
            status: $internalStatus,
            updatedOrder: true // Dispatched event triggers update
            // receiptUrl: extract from payload if available (omitted for now as not required by spec logic explicitly, but DTO allows null)
        );
    }
}
