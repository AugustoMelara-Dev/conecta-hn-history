<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\CustomerTouchpoint;
use App\Models\CustomerTouchpointEvent;
use App\Models\Payment\InboundWebhook;
use Illuminate\Support\Carbon;

final class ResendWebhookTrackingRecorder
{
    public function __construct(
        private readonly CustomerTouchpointEventRepositoryInterface $events,
        private readonly InboundWebhookRepositoryInterface $webhooks,
        private readonly ResendTouchpointEventStatusResolver $statusResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function begin(string $webhookId, string $tenantId, string $eventType, array $payload): InboundWebhook
    {
        $webhook = $this->webhooks->firstOrCreate(
            ['provider' => 'resend', 'provider_transaction_id' => $webhookId],
            ['tenant_id' => $tenantId, 'event_type' => $eventType, 'payload' => $payload, 'status' => 'pending'],
        );

        $webhook->forceFill([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'processing',
            'error_message' => null,
        ]);

        return $this->webhooks->save($webhook);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(CustomerTouchpoint $touchpoint, string $eventType, Carbon $occurredAt, array $payload): CustomerTouchpointEvent
    {
        return $this->events->firstOrCreate(
            [
                'customer_touchpoint_id' => (int) $touchpoint->getKey(),
                'provider' => 'resend',
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
            ],
            [
                'tenant_id' => (string) $touchpoint->tenant_id,
                'status' => $this->statusResolver->resolve($eventType),
                'payload' => $payload,
            ],
        );
    }

    public function existingEvent(CustomerTouchpoint $touchpoint, string $eventType, Carbon $occurredAt): ?CustomerTouchpointEvent
    {
        return $this->events->findExisting((int) $touchpoint->getKey(), 'resend', $eventType, $occurredAt);
    }

    public function markCompleted(InboundWebhook $webhook): void
    {
        $webhook->forceFill(['status' => 'completed']);
        $this->webhooks->save($webhook);
    }

    public function markFailed(InboundWebhook $webhook, \Throwable $exception): void
    {
        $webhook->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        $this->webhooks->save($webhook);
    }
}
