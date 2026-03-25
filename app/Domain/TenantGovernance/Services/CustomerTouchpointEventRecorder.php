<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerNote;
use App\Models\CustomerTouchpoint;
use App\Models\CustomerTouchpointEvent;
use App\Models\Payment\InboundWebhook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CustomerTouchpointEventRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function recordResendWebhook(array $payload, array $headers = []): ?CustomerTouchpointEvent
    {
        $eventType = (string) ($payload['type'] ?? 'unknown');
        $data = (array) ($payload['data'] ?? []);
        $tags = $this->normalizeTags($data['tags'] ?? []);
        $webhookId = (string) ($headers['svix-id'] ?? Str::uuid()->toString());
        $providerMessageId = (string) ($data['email_id'] ?? $data['emailId'] ?? '');
        $occurredAt = $this->eventTimestamp($payload);

        $touchpoint = $this->resolveTouchpoint($tags, $providerMessageId);
        $tenantId = (string) ($touchpoint?->tenant_id ?? $tags['tenant_id'] ?? 'system');

        $webhook = InboundWebhook::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
                [
                    'provider' => 'resend',
                    'provider_transaction_id' => $webhookId,
                ],
                [
                    'tenant_id' => $tenantId,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'status' => 'pending',
                ],
            );

        if ($webhook->status === 'completed' && $touchpoint instanceof CustomerTouchpoint) {
            return CustomerTouchpointEvent::query()
                ->withoutGlobalScopes()
                ->where('customer_touchpoint_id', $touchpoint->getKey())
                ->where('provider', 'resend')
                ->where('event_type', $eventType)
                ->where('occurred_at', $occurredAt)
                ->first();
        }

        $webhook->forceFill([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'processing',
            'error_message' => null,
        ])->save();

        try {
            if (! $touchpoint instanceof CustomerTouchpoint) {
                $webhook->forceFill(['status' => 'completed'])->save();

                return null;
            }

            $event = CustomerTouchpointEvent::query()
                ->withoutGlobalScopes()
                ->firstOrCreate(
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

            if (in_array($eventType, ['email.received', 'email.replied'], true)) {
                $this->logInboundReply($touchpoint, $payload, $occurredAt);
            }

            $webhook->forceFill(['status' => 'completed'])->save();

            return $event;
        } catch (\Throwable $exception) {
            $webhook->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  array<string, string>  $tags
     */
    private function resolveTouchpoint(array $tags, string $providerMessageId): ?CustomerTouchpoint
    {
        $touchpointId = $tags['touchpoint_id'] ?? null;

        if (filled($touchpointId) && is_numeric($touchpointId)) {
            return CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->find((int) $touchpointId);
        }

        if ($providerMessageId === '') {
            return null;
        }

        return CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('provider', 'resend')
            ->where('provider_message_id', $providerMessageId)
            ->first();
    }

    /**
     * @param  array<mixed>  $rawTags
     * @return array<string, string>
     */
    private function normalizeTags(array $rawTags): array
    {
        return collect($rawTags)
            ->mapWithKeys(function (mixed $item, mixed $key): array {
                if (is_array($item) && isset($item['name'], $item['value'])) {
                    return [(string) $item['name'] => (string) $item['value']];
                }

                if (is_string($key) && is_scalar($item)) {
                    return [$key => (string) $item];
                }

                return [];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyTouchpointState(CustomerTouchpoint $touchpoint, string $eventType, array $payload, Carbon $occurredAt): void
    {
        $touchpoint->forceFill([
            'delivery_status' => $this->deliveryStatusForEvent($eventType),
            'provider' => 'resend',
            'provider_payload' => $payload,
        ]);

        match ($eventType) {
            'email.sent' => $touchpoint->sent_at ??= $occurredAt,
            'email.delivered' => $touchpoint->delivered_at = $occurredAt,
            'email.opened' => $touchpoint->opened_at = $occurredAt,
            'email.clicked' => $touchpoint->clicked_at = $occurredAt,
            'email.delivery_delayed' => $touchpoint->last_error = 'La entrega del correo se retraso en el proveedor.',
            'email.bounced' => $this->markFailed($touchpoint, $occurredAt, $payload, 'Rebote del proveedor de correo.'),
            'email.complained' => $this->markFailed($touchpoint, $occurredAt, $payload, 'El destinatario reporto una queja del correo.'),
            'email.received', 'email.replied' => $touchpoint->replied_at = $occurredAt,
            default => null,
        };

        if ($touchpoint->status === CustomerTouchpoint::STATUS_QUEUED) {
            $touchpoint->status = CustomerTouchpoint::STATUS_SENT;
        }

        $touchpoint->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markFailed(CustomerTouchpoint $touchpoint, Carbon $occurredAt, array $payload, string $fallbackMessage): void
    {
        $touchpoint->status = CustomerTouchpoint::STATUS_FAILED;
        $touchpoint->failed_at = $occurredAt;
        $touchpoint->last_error = (string) data_get($payload, 'data.reason', $fallbackMessage);

        if (str_contains((string) ($payload['type'] ?? ''), 'bounce')) {
            $touchpoint->bounced_at = $occurredAt;
        } else {
            $touchpoint->complained_at = $occurredAt;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logInboundReply(CustomerTouchpoint $touchpoint, array $payload, Carbon $occurredAt): void
    {
        $body = trim((string) data_get($payload, 'data.text', data_get($payload, 'data.subject', 'Respuesta recibida por email.')));

        CustomerNote::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $touchpoint->tenant_id,
                'customer_profile_id' => (int) $touchpoint->customer_profile_id,
                'note_type' => 'inbound_reply',
                'body' => $body !== '' ? $body : 'Respuesta recibida por email.',
                'metadata' => [
                    'provider' => 'resend',
                    'source_touchpoint_id' => (int) $touchpoint->getKey(),
                ],
                'pinned_at' => null,
                'next_action_at' => $occurredAt->copy()->addDay(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
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
