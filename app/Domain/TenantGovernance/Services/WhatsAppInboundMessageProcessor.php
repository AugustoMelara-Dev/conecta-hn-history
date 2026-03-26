<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class WhatsAppInboundMessageProcessor
{
    public function __construct(
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly WhatsAppWebhookTrackingRecorder $trackingRecorder,
        private readonly WhatsAppInboundReplyHandler $inboundReplyHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $messagePayload
     * @param  array<string, string>  $headers
     */
    public function record(array $value, array $messagePayload, array $headers): bool
    {
        $contextMessageId = (string) data_get($messagePayload, 'context.id', '');
        $occurredAt = $this->parseUnixTimestamp((string) ($messagePayload['timestamp'] ?? now()->timestamp));
        $touchpoint = $contextMessageId !== ''
            ? $this->touchpoints->findByProviderMessage('whatsapp_cloud', $contextMessageId)
            : null;
        $providerTransactionId = (string) ($messagePayload['id'] ?? uniqid('whatsapp_inbound_', true));

        if (! $touchpoint instanceof CustomerTouchpoint) {
            return false;
        }

        $webhook = $this->trackingRecorder->recordInbound($touchpoint, $value, $messagePayload, $occurredAt, $providerTransactionId);

        $touchpoint->forceFill([
            'delivery_status' => CustomerTouchpoint::DELIVERY_REPLIED,
            'replied_at' => $occurredAt,
            'provider' => 'whatsapp_cloud',
            'provider_payload' => ['value' => $value, 'message' => $messagePayload],
            'next_retry_at' => null,
            'status' => CustomerTouchpoint::STATUS_SENT,
        ]);
        $this->touchpoints->save($touchpoint);

        $this->inboundReplyHandler->handle($touchpoint, $messagePayload, $occurredAt, $contextMessageId);

        $webhook->forceFill(['tenant_id' => (string) $touchpoint->tenant_id, 'status' => 'completed']);
        app(InboundWebhookRepositoryInterface::class)->save($webhook);

        return true;
    }

    private function parseUnixTimestamp(string $timestamp): Carbon
    {
        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp);
        }

        return Carbon::parse($timestamp);
    }
}
