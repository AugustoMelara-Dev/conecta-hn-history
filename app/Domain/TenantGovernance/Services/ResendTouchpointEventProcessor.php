<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface;
use App\Models\CustomerTouchpoint;
use App\Models\CustomerTouchpointEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class ResendTouchpointEventProcessor
{
    public function __construct(
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly CustomerTouchpointEventRepositoryInterface $events,
        private readonly InboundWebhookRepositoryInterface $webhooks,
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
        $tenantId = (string) ($touchpoint?->tenant_id ?? $tags['tenant_id'] ?? 'system');

        $webhook = $this->webhooks->firstOrCreate(
            ['provider' => 'resend', 'provider_transaction_id' => $webhookId],
            ['tenant_id' => $tenantId, 'event_type' => $eventType, 'payload' => $payload, 'status' => 'pending'],
        );

        if ($webhook->status === 'completed' && $touchpoint instanceof CustomerTouchpoint) {
            return $this->events->findExisting((int) $touchpoint->getKey(), 'resend', $eventType, $occurredAt);
        }

        $webhook->forceFill([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'processing',
            'error_message' => null,
        ]);
        $webhook = $this->webhooks->save($webhook);

        try {
            if (! $touchpoint instanceof CustomerTouchpoint) {
                $webhook->forceFill(['status' => 'completed']);
                $this->webhooks->save($webhook);

                return null;
            }

            $event = $this->events->firstOrCreate(
                [
                    'customer_touchpoint_id' => (int) $touchpoint->getKey(),
                    'provider' => 'resend',
                    'event_type' => $eventType,
                    'occurred_at' => $occurredAt,
                ],
                [
                    'tenant_id' => (string) $touchpoint->tenant_id,
                    'status' => $this->eventStatus($eventType),
                    'payload' => $payload,
                ],
            );

            $this->outcomeApplier->apply($touchpoint, $eventType, $payload, $occurredAt);

            if (in_array($eventType, ['email.received', 'email.replied'], true)) {
                $this->inboundReplyHandler->handle($touchpoint, $payload, $occurredAt, $providerMessageId, $tags);
            }

            $webhook->forceFill(['status' => 'completed']);
            $this->webhooks->save($webhook);

            return $event;
        } catch (\Throwable $exception) {
            $webhook->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            $this->webhooks->save($webhook);
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

    private function eventStatus(string $eventType): string
    {
        return match ($eventType) {
            'email.delivered' => 'delivered',
            'email.opened' => 'opened',
            'email.clicked' => 'clicked',
            'email.bounced' => 'bounced',
            'email.complained' => 'complained',
            'email.received', 'email.replied' => 'replied',
            'email.delivery_delayed' => 'delayed',
            default => 'sent',
        };
    }

}
