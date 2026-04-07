<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Models\CustomerTouchpoint;
use App\Models\CustomerTouchpointEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class ResendTouchpointEventProcessor
{
    public function __construct(
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly ResendWebhookTrackingRecorder $trackingRecorder,
        private readonly ResendTouchpointOutcomeApplier $outcomeApplier,
        private readonly ResendInboundReplyHandler $inboundReplyHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function record(array $payload, array $headers = []): ?CustomerTouchpointEvent
    {
        $eventType = (string) ($payload['type'] ?? 'unknown');
        $data = (array) ($payload['data'] ?? []);
        $tags = $this->normalizeTags($data['tags'] ?? []);
        $webhookId = (string) ($headers['svix-id'] ?? Str::uuid()->toString());
        $providerMessageId = (string) ($data['email_id'] ?? $data['emailId'] ?? '');
        $occurredAt = $this->eventTimestamp($payload);

        $touchpoint = $this->resolveTouchpoint($tags, $providerMessageId);
        $tenantId = $touchpoint instanceof CustomerTouchpoint
            ? (string) $touchpoint->tenant_id
            : (string) ($tags['tenant_id'] ?? 'system');

        $webhook = $this->trackingRecorder->begin($webhookId, $tenantId, $eventType, $payload);

        if ($webhook->status === 'completed' && $touchpoint instanceof CustomerTouchpoint) {
            return $this->trackingRecorder->existingEvent($touchpoint, $eventType, $occurredAt);
        }

        try {
            if (! $touchpoint instanceof CustomerTouchpoint) {
                $this->trackingRecorder->markCompleted($webhook);

                return null;
            }

            $event = $this->trackingRecorder->recordEvent($touchpoint, $eventType, $occurredAt, $payload);

            $this->outcomeApplier->apply($touchpoint, $eventType, $payload, $occurredAt);

            if (in_array($eventType, ['email.received', 'email.replied'], true)) {
                $this->inboundReplyHandler->handle($touchpoint, $payload, $occurredAt, $providerMessageId, $tags);
            }

            $this->trackingRecorder->markCompleted($webhook);

            return $event;
        } catch (\Throwable $exception) {
            $this->trackingRecorder->markFailed($webhook, $exception);
            throw $exception;
        }
    }

    private function resolveTouchpoint(array $tags, string $providerMessageId): ?CustomerTouchpoint
    {
        $touchpointId = $tags['touchpoint_id'] ?? null;
        if (filled($touchpointId) && is_numeric($touchpointId)) {
            return $this->touchpoints->findById((int) $touchpointId);
        }

        return $providerMessageId === '' ? null : $this->touchpoints->findByProviderMessage('resend', $providerMessageId);
    }

    private function normalizeTags(array $rawTags): array
    {
        return collect($rawTags)->mapWithKeys(function (mixed $item, mixed $key): array {
            if (is_array($item) && isset($item['name'], $item['value'])) {
                return [(string) $item['name'] => (string) $item['value']];
            }
            if (is_string($key) && is_scalar($item)) {
                return [$key => (string) $item];
            }

            return [];
        })->all();
    }

    private function eventTimestamp(array $payload): Carbon
    {
        $value = $payload['created_at'] ?? $payload['createdAt'] ?? now()->toIso8601String();

        return Carbon::parse((string) $value);
    }
}
