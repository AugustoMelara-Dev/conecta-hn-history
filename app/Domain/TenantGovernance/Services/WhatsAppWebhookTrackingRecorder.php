<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\CustomerTouchpoint;
use App\Models\Payment\InboundWebhook;
use Illuminate\Support\Carbon;

final class WhatsAppWebhookTrackingRecorder
{
    public function __construct(
        private readonly CustomerTouchpointEventRepositoryInterface $events,
        private readonly InboundWebhookRepositoryInterface $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $statusPayload
     */
    public function recordStatus(CustomerTouchpoint $touchpoint, array $value, array $statusPayload, Carbon $occurredAt, string $providerTransactionId): InboundWebhook
    {
        $status = (string) ($statusPayload['status'] ?? 'sent');

        $webhook = $this->webhooks->firstOrCreate(
            [
                'provider' => 'whatsapp_cloud',
                'provider_transaction_id' => $providerTransactionId,
            ],
            [
                'tenant_id' => (string) $touchpoint->tenant_id,
                'event_type' => "whatsapp.{$status}",
                'payload' => ['value' => $value, 'status' => $statusPayload],
                'status' => 'pending',
            ],
        );

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

        return $webhook;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $messagePayload
     */
    public function recordInbound(CustomerTouchpoint $touchpoint, array $value, array $messagePayload, Carbon $occurredAt, string $providerTransactionId): InboundWebhook
    {
        $webhook = $this->webhooks->firstOrCreate(
            [
                'provider' => 'whatsapp_cloud',
                'provider_transaction_id' => $providerTransactionId,
            ],
            [
                'tenant_id' => (string) $touchpoint->tenant_id,
                'event_type' => 'whatsapp.inbound',
                'payload' => ['value' => $value, 'message' => $messagePayload],
                'status' => 'pending',
            ],
        );

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

        return $webhook;
    }
}
