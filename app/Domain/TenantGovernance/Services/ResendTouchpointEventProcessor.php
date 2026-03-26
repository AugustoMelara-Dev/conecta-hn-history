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
        private readonly CustomerTouchpointGovernance $governance,
        private readonly CustomerTouchpointRepositoryInterface $touchpoints,
        private readonly CustomerTouchpointEventRepositoryInterface $events,
        private readonly InboundWebhookRepositoryInterface $webhooks,
        private readonly CustomerTouchpointInboundReplyRecorder $inboundReplies,
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
                    'status' => $this->deliveryStatusForEvent($eventType),
                    'payload' => $payload,
                ],
            );

            $this->applyTouchpointState($touchpoint, $eventType, $payload, $occurredAt);
            $this->inboundReplies->syncTouchpoint($touchpoint);

            if (in_array($eventType, ['email.received', 'email.replied'], true)) {
                $this->inboundReplies->record(
                    $touchpoint,
                    (string) data_get($payload, 'data.text', data_get($payload, 'data.subject', 'Respuesta recibida por email.')),
                    $occurredAt,
                    'resend',
                    'email',
                    [
                        'source_touchpoint_id' => (int) $touchpoint->getKey(),
                        'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : $touchpoint->provider_message_id,
                        'provider_thread_key' => (string) ($tags['provider_thread_key']
                            ?? data_get($touchpoint->metadata, 'provider_thread_key')
                            ?? data_get($touchpoint->metadata, 'conversation_key')
                            ?? $touchpoint->provider_message_id
                            ?? ''),
                        'provider_reply_to' => (string) data_get($payload, 'data.in_reply_to', $touchpoint->provider_message_id),
                    ],
                );
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

    private function applyTouchpointState(CustomerTouchpoint $touchpoint, string $eventType, array $payload, Carbon $occurredAt): void
    {
        $touchpoint->forceFill([
            'delivery_status' => $this->deliveryStatusForEvent($eventType),
            'provider' => 'resend',
            'provider_payload' => $payload,
        ]);

        match ($eventType) {
            'email.sent' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->sent_at ??= $occurredAt),
            'email.delivered' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->delivered_at = $occurredAt),
            'email.opened' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->opened_at = $occurredAt),
            'email.clicked' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->clicked_at = $occurredAt),
            'email.delivery_delayed' => $this->markDeliveryDelayed($touchpoint, $occurredAt),
            'email.bounced' => $this->markFailed($touchpoint, $occurredAt, $payload, 'Rebote del proveedor de correo.'),
            'email.complained' => $this->markFailed($touchpoint, $occurredAt, $payload, 'El destinatario reporto una queja del correo.', false),
            'email.received', 'email.replied' => $this->markDeliveryHealthy($touchpoint, fn () => $touchpoint->replied_at = $occurredAt),
            default => null,
        };

        if ($touchpoint->status === CustomerTouchpoint::STATUS_QUEUED) {
            $touchpoint->status = CustomerTouchpoint::STATUS_SENT;
        }

        $this->touchpoints->save($touchpoint);
    }

    private function markFailed(CustomerTouchpoint $touchpoint, Carbon $occurredAt, array $payload, string $fallbackMessage, bool $scheduleRetry = true): void
    {
        $touchpoint->status = CustomerTouchpoint::STATUS_FAILED;
        $touchpoint->failed_at = $occurredAt;
        $touchpoint->last_error = (string) data_get($payload, 'data.reason', $fallbackMessage);
        $touchpoint->next_retry_at = $scheduleRetry ? $this->governance->nextRetryAt($touchpoint, $occurredAt) : null;
        if (str_contains((string) ($payload['type'] ?? ''), 'bounce')) {
            $touchpoint->bounced_at = $occurredAt;
        } else {
            $touchpoint->complained_at = $occurredAt;
        }
    }

    private function markDeliveryHealthy(CustomerTouchpoint $touchpoint, \Closure $mutate): void
    {
        $mutate();
        $touchpoint->next_retry_at = null;
        if ($touchpoint->status === CustomerTouchpoint::STATUS_FAILED) {
            $touchpoint->status = CustomerTouchpoint::STATUS_SENT;
        }
    }

    private function markDeliveryDelayed(CustomerTouchpoint $touchpoint, Carbon $occurredAt): void
    {
        $touchpoint->last_error = 'La entrega del correo se retraso en el proveedor.';
        $touchpoint->next_retry_at = $this->governance->nextRetryAt($touchpoint, $occurredAt);
    }

    private function eventTimestamp(array $payload): Carbon
    {
        $value = $payload['created_at'] ?? $payload['createdAt'] ?? now()->toIso8601String();
        return Carbon::parse((string) $value);
    }

    private function deliveryStatusForEvent(string $eventType): string
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
