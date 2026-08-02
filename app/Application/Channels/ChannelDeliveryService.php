<?php

declare(strict_types=1);

namespace App\Application\Channels;

use App\Application\Channels\Contracts\ChannelAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ChannelDeliveryService
{
    public function __construct(
        private readonly ChannelConnectionService $connections,
        private readonly ?ConversationChannelBridge $conversationBridge = null,
    ) {}

    public function send(
        ChannelAdapterInterface $adapter,
        ChannelContext $context,
        ChannelOutboundMessage $message,
    ): DeliveryReceipt {
        $context->assertOutboundLineage($message);
        $this->connections->connect($context);
        $decision = $this->connections->snapshotCapability($adapter, $context);
        $payloadHash = $this->payloadHash($message);
        $existing = $this->findIntent($context, $message);
        $providerKey = is_object($existing)
            ? (string) $existing->provider_idempotency_key
            : $this->providerIdempotencyKey($context, $message);
        $intent = $this->ensureIntent($context, $message, $payloadHash, $providerKey);

        if ((string) $intent->payload_hash !== $payloadHash) {
            throw new ChannelConflictException('Channel delivery idempotency conflict.');
        }

        if (in_array((string) $intent->delivery_state, ['delivered', 'blocked'], true)) {
            $receipt = $this->toReceipt($intent);
            $this->linkOutboundIfBound($message, $receipt);

            return $receipt;
        }

        if (! $decision->allowed) {
            $receipt = new DeliveryReceipt(
                tenantId: $context->tenantId,
                channel: $context->channel,
                connectionKey: $context->connectionKey,
                idempotencyKey: $message->idempotencyKey,
                providerMessageId: null,
                providerEventId: null,
                deliveryState: 'blocked',
                costAmount: '0.00',
                costCurrency: $context->currency,
                providerIdempotencyKey: $providerKey,
                metadata: [
                    'reason' => $decision->reason,
                    'market_country_code' => $context->marketCountryCode,
                ],
            );
            $this->finalizeIntent($context, $message, $intent, $payloadHash, $receipt, null);

            return $receipt;
        }

        $intent = $this->markPending($context, $message, $intent, $payloadHash, $providerKey);
        $providerMessage = $message->withProviderIdempotencyKey($providerKey);

        try {
            $receipt = $adapter->send($providerMessage)->withProviderIdempotencyKey($providerKey);
        } catch (Throwable $exception) {
            $receipt = new DeliveryReceipt(
                tenantId: $context->tenantId,
                channel: $context->channel,
                connectionKey: $context->connectionKey,
                idempotencyKey: $message->idempotencyKey,
                providerMessageId: null,
                providerEventId: null,
                deliveryState: 'provider_unavailable',
                costAmount: '0.00',
                costCurrency: $context->currency,
                providerIdempotencyKey: $providerKey,
                metadata: [
                    'reason' => 'provider_unavailable',
                    'market_country_code' => $context->marketCountryCode,
                ],
            );
            $this->finalizeIntent($context, $message, $intent, $payloadHash, $receipt, $exception->getMessage());

            return $receipt;
        }

        $this->finalizeIntent($context, $message, $intent, $payloadHash, $receipt, null);
        $this->linkOutboundIfBound($message, $receipt);

        return $receipt;
    }

    private function linkOutboundIfBound(ChannelOutboundMessage $message, DeliveryReceipt $receipt): void
    {
        if ($this->conversationBridge === null || $receipt->deliveryState !== 'delivered') {
            return;
        }
        $conversationId = $message->metadata['conversation_id'] ?? null;
        if (! is_string($conversationId) || ! Str::isUuid($conversationId)) {
            return;
        }

        $this->conversationBridge->linkOutbound($message->tenantId, $conversationId, $message);
    }

    private function payloadHash(ChannelOutboundMessage $message): string
    {
        return hash('sha256', json_encode([
            'recipient' => $message->recipient,
            'subject' => $message->subject,
            'content' => $message->content,
            'attachments' => $message->attachments,
            'metadata' => $message->metadata,
            'thread' => $message->externalThreadReference,
        ], JSON_THROW_ON_ERROR));
    }

    private function providerIdempotencyKey(ChannelContext $context, ChannelOutboundMessage $message): string
    {
        return 'dovra07-'.hash('sha256', implode('|', [
            strtolower($context->tenantId),
            $context->channel,
            $context->connectionKey,
            $message->idempotencyKey,
        ]));
    }

    private function findIntent(ChannelContext $context, ChannelOutboundMessage $message): ?object
    {
        $intent = DB::table('channel_delivery_outbox')
            ->where('tenant_id', $context->tenantId)
            ->where('channel', $context->channel)
            ->where('connection_key', $context->connectionKey)
            ->where('idempotency_key', $message->idempotencyKey)
            ->first();

        return is_object($intent) ? $intent : null;
    }

    private function ensureIntent(
        ChannelContext $context,
        ChannelOutboundMessage $message,
        string $payloadHash,
        string $providerKey,
    ): object {
        $existing = $this->findIntent($context, $message);
        if (is_object($existing)) {
            return $existing;
        }

        $now = now();
        DB::table('channel_delivery_outbox')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $context->tenantId,
            'channel' => $context->channel,
            'connection_key' => $context->connectionKey,
            'idempotency_key' => $message->idempotencyKey,
            'provider_idempotency_key' => $providerKey,
            'external_thread_reference' => $message->externalThreadReference,
            'recipient' => $message->recipient,
            'payload' => json_encode([
                'subject' => $message->subject,
                'content' => $message->content,
                'attachments' => $message->attachments,
                'metadata' => $message->metadata,
            ], JSON_THROW_ON_ERROR),
            'payload_hash' => $payloadHash,
            'delivery_state' => 'pending',
            'attempt_count' => 0,
            'cost_amount' => '0.00',
            'cost_currency' => $context->currency,
            'metadata' => json_encode([
                'market_country_code' => $context->marketCountryCode,
                'locale' => $context->locale,
                'timezone' => $context->timezone,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $intent = $this->findIntent($context, $message);
        if (! is_object($intent)) {
            throw new RuntimeException('Channel delivery intent could not be persisted.');
        }

        return $intent;
    }

    private function markPending(
        ChannelContext $context,
        ChannelOutboundMessage $message,
        object $intent,
        string $payloadHash,
        string $providerKey,
    ): object {
        $now = now();
        DB::table('channel_delivery_outbox')
            ->where('id', $intent->id)
            ->update([
                'delivery_state' => 'pending',
                'attempt_count' => DB::raw('attempt_count + 1'),
                'provider_idempotency_key' => $providerKey,
                'payload_hash' => $payloadHash,
                'attempted_at' => $now,
                'completed_at' => null,
                'last_error' => null,
                'updated_at' => $now,
            ]);

        $intent = $this->findIntent($context, $message);
        if (! is_object($intent)) {
            throw new RuntimeException('Channel delivery intent disappeared before provider side effect.');
        }

        $this->syncAttempt($context, $message, $intent, $payloadHash, 'pending', [], null);

        return $intent;
    }

    private function finalizeIntent(
        ChannelContext $context,
        ChannelOutboundMessage $message,
        object $intent,
        string $payloadHash,
        DeliveryReceipt $receipt,
        ?string $lastError,
    ): void {
        $now = now();
        $metadata = [
            ...$receipt->metadata,
            'provider_idempotency_key' => $receipt->providerIdempotencyKey,
        ];
        $terminal = in_array($receipt->deliveryState, ['delivered', 'blocked'], true);

        DB::table('channel_delivery_outbox')
            ->where('id', $intent->id)
            ->update([
                'delivery_state' => $receipt->deliveryState,
                'provider_idempotency_key' => $receipt->providerIdempotencyKey,
                'provider_message_id' => $receipt->providerMessageId,
                'provider_event_id' => $receipt->providerEventId,
                'cost_amount' => $receipt->costAmount,
                'cost_currency' => $receipt->costCurrency,
                'payload_hash' => $payloadHash,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'last_error' => $lastError,
                'completed_at' => $terminal ? $now : null,
                'updated_at' => $now,
            ]);

        $updated = $this->findIntent($context, $message);
        if (! is_object($updated)) {
            throw new RuntimeException('Channel delivery intent disappeared after provider side effect.');
        }

        $this->syncAttempt(
            $context,
            $message,
            $updated,
            $payloadHash,
            $receipt->deliveryState,
            $metadata,
            $lastError,
            $receipt,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function syncAttempt(
        ChannelContext $context,
        ChannelOutboundMessage $message,
        object $intent,
        string $payloadHash,
        string $state,
        array $metadata,
        ?string $lastError,
        ?DeliveryReceipt $receipt = null,
    ): void {
        $existing = DB::table('channel_delivery_attempts')
            ->where('tenant_id', $context->tenantId)
            ->where('channel', $context->channel)
            ->where('connection_key', $context->connectionKey)
            ->where('idempotency_key', $message->idempotencyKey)
            ->first(['id', 'created_at']);
        $now = now();

        DB::table('channel_delivery_attempts')->updateOrInsert(
            [
                'tenant_id' => $context->tenantId,
                'channel' => $context->channel,
                'connection_key' => $context->connectionKey,
                'idempotency_key' => $message->idempotencyKey,
            ],
            [
                'id' => is_object($existing) ? (string) $existing->id : (string) Str::uuid(),
                'provider_idempotency_key' => (string) $intent->provider_idempotency_key,
                'attempt_count' => (int) $intent->attempt_count,
                'external_thread_reference' => $message->externalThreadReference,
                'recipient' => $message->recipient,
                'provider_message_id' => $receipt?->providerMessageId,
                'provider_event_id' => $receipt?->providerEventId,
                'delivery_state' => $state,
                'payload_hash' => $payloadHash,
                'cost_amount' => $receipt?->costAmount ?? '0.00',
                'cost_currency' => $receipt?->costCurrency ?? $context->currency,
                'metadata' => json_encode([
                    'outbox_id' => (string) $intent->id,
                    ...$metadata,
                ], JSON_THROW_ON_ERROR),
                'last_error' => $lastError,
                'attempted_at' => $intent->attempted_at,
                'completed_at' => in_array($state, ['delivered', 'blocked'], true) ? $now : null,
                'created_at' => is_object($existing) ? $existing->created_at : $now,
                'updated_at' => $now,
            ],
        );
    }

    private function toReceipt(object $intent): DeliveryReceipt
    {
        return new DeliveryReceipt(
            tenantId: (string) $intent->tenant_id,
            channel: (string) $intent->channel,
            connectionKey: (string) $intent->connection_key,
            idempotencyKey: (string) $intent->idempotency_key,
            providerMessageId: $intent->provider_message_id === null ? null : (string) $intent->provider_message_id,
            providerEventId: $intent->provider_event_id === null ? null : (string) $intent->provider_event_id,
            deliveryState: (string) $intent->delivery_state,
            costAmount: number_format((float) $intent->cost_amount, 2, '.', ''),
            costCurrency: (string) $intent->cost_currency,
            providerIdempotencyKey: (string) $intent->provider_idempotency_key,
            metadata: json_decode((string) ($intent->metadata ?? '{}'), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
