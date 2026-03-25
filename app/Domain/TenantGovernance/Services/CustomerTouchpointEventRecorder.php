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
                $this->logInboundReply(
                    $touchpoint,
                    (string) data_get($payload, 'data.text', data_get($payload, 'data.subject', 'Respuesta recibida por email.')),
                    $occurredAt,
                    'resend',
                    'email',
                    [
                        'source_touchpoint_id' => (int) $touchpoint->getKey(),
                    ],
                );
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
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function recordWhatsAppWebhook(array $payload, array $headers = []): int
    {
        $tracked = 0;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $statusPayload) {
                    $tracked += $this->recordWhatsAppStatus($value, (array) $statusPayload, $headers) ? 1 : 0;
                }

                foreach ((array) ($value['messages'] ?? []) as $messagePayload) {
                    $tracked += $this->recordWhatsAppInboundMessage($value, (array) $messagePayload, $headers) ? 1 : 0;
                }
            }
        }

        return $tracked;
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
    private function logInboundReply(
        CustomerTouchpoint $touchpoint,
        string $body,
        Carbon $occurredAt,
        string $provider,
        string $channel,
        array $metadata = [],
    ): void
    {
        $message = trim($body);

        CustomerNote::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $touchpoint->tenant_id,
                'customer_profile_id' => (int) $touchpoint->customer_profile_id,
                'note_type' => 'inbound_reply',
                'body' => $message !== '' ? $message : 'Respuesta recibida del cliente.',
                'metadata' => [
                    'provider' => $provider,
                    'channel' => $channel,
                    ...$metadata,
                ],
                'pinned_at' => null,
                'next_action_at' => $occurredAt->copy()->addDay(),
            ]);

        $touchpoint->customerProfile()
            ->withoutGlobalScopes()
            ->update([
                'last_inbound_reply_at' => $occurredAt,
                'last_engaged_channel' => $channel,
            ]);

        if ($channel === 'whatsapp') {
            $this->applyOptInKeywords($touchpoint, $message);
        }
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

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $statusPayload
     * @param  array<string, string>  $headers
     */
    private function recordWhatsAppStatus(array $value, array $statusPayload, array $headers): bool
    {
        $providerMessageId = (string) ($statusPayload['id'] ?? '');
        $status = (string) ($statusPayload['status'] ?? 'sent');
        $occurredAt = $this->parseUnixTimestamp((string) ($statusPayload['timestamp'] ?? now()->timestamp));
        $touchpoint = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('provider', 'whatsapp_cloud')
            ->where('provider_message_id', $providerMessageId)
            ->first();
        $tenantId = (string) ($touchpoint?->tenant_id ?? 'system');
        $providerTransactionId = $providerMessageId !== ''
            ? "{$providerMessageId}:{$status}:{$occurredAt->timestamp}"
            : (string) ($headers['x-hub-signature-256'] ?? uniqid('whatsapp_', true));

        $webhook = InboundWebhook::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
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
            $webhook->forceFill(['status' => 'completed'])->save();

            return false;
        }

        CustomerTouchpointEvent::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
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
            'last_error' => $status === 'failed'
                ? (string) data_get($statusPayload, 'errors.0.title', data_get($statusPayload, 'errors.0.details', 'WhatsApp delivery failed.'))
                : $touchpoint->last_error,
            'status' => $status === 'failed'
                ? CustomerTouchpoint::STATUS_FAILED
                : CustomerTouchpoint::STATUS_SENT,
        ])->save();

        $webhook->forceFill(['tenant_id' => (string) $touchpoint->tenant_id, 'status' => 'completed'])->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $messagePayload
     * @param  array<string, string>  $headers
     */
    private function recordWhatsAppInboundMessage(array $value, array $messagePayload, array $headers): bool
    {
        $contextMessageId = (string) data_get($messagePayload, 'context.id', '');
        $occurredAt = $this->parseUnixTimestamp((string) ($messagePayload['timestamp'] ?? now()->timestamp));
        $touchpoint = $contextMessageId !== ''
            ? CustomerTouchpoint::query()
                ->withoutGlobalScopes()
                ->where('provider', 'whatsapp_cloud')
                ->where('provider_message_id', $contextMessageId)
                ->first()
            : null;
        $tenantId = (string) ($touchpoint?->tenant_id ?? 'system');
        $providerTransactionId = (string) ($messagePayload['id'] ?? uniqid('whatsapp_inbound_', true));
        $body = trim((string) data_get($messagePayload, 'text.body', data_get($messagePayload, 'button.text', 'Respuesta recibida por WhatsApp.')));

        $webhook = InboundWebhook::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
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
            $webhook->forceFill(['status' => 'completed'])->save();

            return false;
        }

        CustomerTouchpointEvent::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
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
            'status' => CustomerTouchpoint::STATUS_SENT,
        ])->save();

        $this->logInboundReply(
            $touchpoint,
            $body !== '' ? $body : 'Respuesta recibida por WhatsApp.',
            $occurredAt,
            'whatsapp_cloud',
            'whatsapp',
            [
                'source_touchpoint_id' => (int) $touchpoint->getKey(),
                'from' => (string) ($messagePayload['from'] ?? ''),
            ],
        );

        $webhook->forceFill(['tenant_id' => (string) $touchpoint->tenant_id, 'status' => 'completed'])->save();

        return true;
    }

    private function parseUnixTimestamp(string $timestamp): Carbon
    {
        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp);
        }

        return Carbon::parse($timestamp);
    }

    private function applyOptInKeywords(CustomerTouchpoint $touchpoint, string $body): void
    {
        $keyword = strtoupper(trim($body));
        $accept = collect((array) config('customer-automation.opt_in_keywords.accept', []))
            ->map(static fn (mixed $value): string => strtoupper((string) $value))
            ->all();
        $stop = collect((array) config('customer-automation.opt_in_keywords.stop', []))
            ->map(static fn (mixed $value): string => strtoupper((string) $value))
            ->all();

        if (! in_array($keyword, [...$accept, ...$stop], true)) {
            return;
        }

        $accepting = in_array($keyword, $accept, true);
        $profile = $touchpoint->customerProfile()->withoutGlobalScopes()->first();

        if (! $profile instanceof \App\Models\CustomerProfile) {
            return;
        }

        $profile->forceFill([
            'whatsapp_opt_in' => $accepting,
            'marketing_opt_in' => $accepting ? true : $profile->marketing_opt_in,
            'last_engaged_channel' => 'whatsapp',
            'last_inbound_reply_at' => now(),
        ])->save();

        CustomerNote::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => (string) $touchpoint->tenant_id,
                'customer_profile_id' => (int) $touchpoint->customer_profile_id,
                'note_type' => 'opt_in_update',
                'body' => $accepting
                    ? 'El cliente confirmo opt-in por WhatsApp.'
                    : 'El cliente solicito baja de WhatsApp.',
                'metadata' => [
                    'provider' => 'whatsapp_cloud',
                    'keyword' => $keyword,
                    'accepted' => $accepting,
                ],
            ]);
    }
}
