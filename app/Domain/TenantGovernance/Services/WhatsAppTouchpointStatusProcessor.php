<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\CustomerTouchpoint;
use Illuminate\Support\Carbon;

final class WhatsAppTouchpointStatusProcessor
{
    public function __construct(
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly WhatsAppWebhookTrackingRecorder $trackingRecorder,
        private readonly WhatsAppTouchpointOutcomeApplier $outcomeApplier,
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
        $providerTransactionId = $providerMessageId !== ''
            ? "{$providerMessageId}:{$status}:{$occurredAt->timestamp}"
            : (string) ($headers['x-hub-signature-256'] ?? uniqid('whatsapp_', true));

        if (! $touchpoint instanceof CustomerTouchpoint) {
            return false;
        }

        $webhook = $this->trackingRecorder->recordStatus($touchpoint, $value, $statusPayload, $occurredAt, $providerTransactionId);

        $this->outcomeApplier->apply($touchpoint, $statusPayload, $value, $occurredAt);

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
