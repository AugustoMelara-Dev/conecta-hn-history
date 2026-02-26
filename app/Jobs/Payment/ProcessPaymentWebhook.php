<?php

declare(strict_types=1);

namespace App\Jobs\Payment;

use App\Application\Billing\Actions\HandlePaymentWebhookAction;
use App\Domain\ValueObjects\Payment\PaymentWebhook;
use App\Models\Payment\InboundWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $webhookId
    ) {}

    public function handle(HandlePaymentWebhookAction $action): void
    {
        $webhook = InboundWebhook::find($this->webhookId);

        if (! $webhook || $webhook->status === 'completed') {
            return;
        }

        try {
            $webhook->update(['status' => 'processing']);

            // Hydrate Domain VO from DB Payload
            $domainObject = PaymentWebhook::fromArray(
                $webhook->event_type,
                $webhook->payload
            );

            // Execute Business Logic
            $action->execute($domainObject);

            $webhook->update(['status' => 'completed']);

        } catch (\Throwable $e) {
            $webhook->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            // Allow Queue retry mechanism to pick this up if needed, 
            // or fail silently if we want manual intervention.
            throw $e; 
        }
    }
}
