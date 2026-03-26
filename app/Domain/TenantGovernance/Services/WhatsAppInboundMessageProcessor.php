<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class WhatsAppInboundMessageProcessor
{
    public function __construct(
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly CustomerTouchpointEventRepositoryInterface $events,
        private readonly InboundWebhookRepositoryInterface $webhooks,
        private readonly CustomerTouchpointInboundReplyRecorder $inboundReplies,
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
        $tenantId = (string) ($touchpoint?->tenant_id ?? 'system');
        $providerTransactionId = (string) ($messagePayload['id'] ?? uniqid('whatsapp_inbound_', true));
        $body = trim((string) data_get($messagePayload, 'text.body', data_get($messagePayload, 'button.text', 'Respuesta recibida por WhatsApp.')));

        $webhook = $this->webhooks->firstOrCreate(
            [
                'provider' => 'whatsapp_cloud',
                'provider_transaction_id' => $providerTransactionId,
            ],
            [
                'tenant_id' => $tenantId,
                'event_type' => 'whatsapp.inbound',
                'payload' => ['value' => $value, 'message' => $messagePayload],
                'status' => 'pending',
            ],
        );

        if (! $touchpoint instanceof CustomerTouchpoint) {
            $webhook->forceFill(['status' => 'completed']);
            $this->webhooks->save($webhook);

            return false;
        }

        $this->events->firstOrCreate(
            [
                'customer_touchpoint_id' => (int) $touchpoint->getKey(),
                'provider' => 'whatsapp_cloud',
                'event_type' => 'whatsapp.inbound',
                'occurred_at' => $occurredAt,
            ],
            [
                'tenant_id' => (string) $touchpoint->tenant_id,
                'status' => 'replied',
                'payload' => ['value' => $value, 'message' => $messagePayload],
            ],
        );

        $touchpoint->forceFill([
            'delivery_status' => CustomerTouchpoint::DELIVERY_REPLIED,
            'replied_at' => $occurredAt,
            'provider' => 'whatsapp_cloud',
            'provider_payload' => ['value' => $value, 'message' => $messagePayload],
            'next_retry_at' => null,
            'status' => CustomerTouchpoint::STATUS_SENT,
        ]);
        $this->touchpoints->save($touchpoint);

        $this->inboundReplies->record(
            $touchpoint,
            $body !== '' ? $body : 'Respuesta recibida por WhatsApp.',
            $occurredAt,
            'whatsapp_cloud',
            'whatsapp',
            [
                'source_touchpoint_id' => (int) $touchpoint->getKey(),
                'from' => (string) ($messagePayload['from'] ?? ''),
                'provider_message_id' => (string) ($messagePayload['id'] ?? ''),
                'provider_thread_key' => (string) (data_get($touchpoint->metadata, 'provider_thread_key')
                    ?? data_get($touchpoint->metadata, 'conversation_key')
                    ?? $contextMessageId
                    ?? ''),
                'provider_reply_to' => $contextMessageId !== '' ? $contextMessageId : null,
            ],
        );

        $webhook->forceFill(['tenant_id' => (string) $touchpoint->tenant_id, 'status' => 'completed']);
        $this->webhooks->save($webhook);

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
