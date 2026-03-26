<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class WhatsAppTouchpointStatusProcessor
{
    public function __construct(
        private readonly CustomerTouchpointGovernance $governance,
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly CustomerTouchpointEventRepositoryInterface $events,
        private readonly InboundWebhookRepositoryInterface $webhooks,
        private readonly CustomerTouchpointInboundReplyRecorder $inboundReplies,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $statusPayload
     * @param  array<string, string>  $headers
     */
    public function record(array $value, array $statusPayload, array $headers): bool
    {
        $providerMessageId = (string) ($statusPayload['id'] ?? '');
        $status = (string) ($statusPayload['status'] ?? 'sent');
        $occurredAt = $this->parseUnixTimestamp((string) ($statusPayload['timestamp'] ?? now()->timestamp));
        $touchpoint = $providerMessageId !== '' ? $this->touchpoints->findByProviderMessage('whatsapp_cloud', $providerMessageId) : null;
        $tenantId = (string) ($touchpoint?->tenant_id ?? 'system');
        $providerTransactionId = $providerMessageId !== ''
            ? "{$providerMessageId}:{$status}:{$occurredAt->timestamp}"
            : (string) ($headers['x-hub-signature-256'] ?? uniqid('whatsapp_', true));

        $webhook = $this->webhooks->firstOrCreate(
            [
                'provider' => 'whatsapp_cloud',
                'provider_transaction_id' => $providerTransactionId,
            ],
            [
                'tenant_id' => $tenantId,
                'event_type' => "whatsapp.{$status}",
                'payload' => ['value' => $value, 'status' => $statusPayload],
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
                'event_type' => "whatsapp.{$status}",
                'occurred_at' => $occurredAt,
            ],
            [
                'tenant_id' => (string) $touchpoint->tenant_id,
                'status' => $status,
                'payload' => ['value' => $value, 'status' => $statusPayload],
            ],
        );

        $touchpoint->forceFill([
            'provider' => 'whatsapp_cloud',
            'provider_payload' => ['value' => $value, 'status' => $statusPayload],
            'delivery_status' => match ($status) {
                'delivered' => CustomerTouchpoint::DELIVERY_DELIVERED,
                'read' => CustomerTouchpoint::DELIVERY_READ,
                'failed' => CustomerTouchpoint::DELIVERY_FAILED,
                default => CustomerTouchpoint::DELIVERY_SENT,
            },
            'sent_at' => $status === 'sent' ? ($touchpoint->sent_at ?? $occurredAt) : $touchpoint->sent_at,
            'delivered_at' => $status === 'delivered' ? $occurredAt : $touchpoint->delivered_at,
            'opened_at' => $status === 'read' ? $occurredAt : $touchpoint->opened_at,
            'failed_at' => $status === 'failed' ? $occurredAt : $touchpoint->failed_at,
            'next_retry_at' => $status === 'failed' ? $this->governance->nextRetryAt($touchpoint, $occurredAt) : null,
            'last_error' => $status === 'failed'
                ? (string) data_get($statusPayload, 'errors.0.title', data_get($statusPayload, 'errors.0.details', 'WhatsApp delivery failed.'))
                : $touchpoint->last_error,
            'status' => $status === 'failed'
                ? CustomerTouchpoint::STATUS_FAILED
                : CustomerTouchpoint::STATUS_SENT,
        ]);
        $this->touchpoints->save($touchpoint);
        $this->inboundReplies->syncTouchpoint($touchpoint);

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
