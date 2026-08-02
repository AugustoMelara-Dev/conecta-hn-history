<?php

declare(strict_types=1);

namespace App\Application\Channels;

use App\Application\Channels\Contracts\ChannelAdapterInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ChannelDeliveryService
{
    public function __construct(private readonly ChannelConnectionService $connections) {}

    public function send(
        ChannelAdapterInterface $adapter,
        ChannelContext $context,
        ChannelOutboundMessage $message,
    ): DeliveryReceipt {
        $this->connections->connect($context);
        $decision = $this->connections->snapshotCapability($adapter, $context);
        $payloadHash = $this->payloadHash($message);
        $existing = DB::table('channel_delivery_attempts')
            ->where('tenant_id', $context->tenantId)
            ->where('channel', $context->channel)
            ->where('connection_key', $context->connectionKey)
            ->where('idempotency_key', $message->idempotencyKey)
            ->first();

        if (is_object($existing)) {
            if ((string) $existing->payload_hash !== $payloadHash) {
                throw new ChannelConflictException('Channel delivery idempotency conflict.');
            }

            return $this->toReceipt($existing);
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
                costCurrency: 'USD',
                metadata: [
                    'reason' => $decision->reason,
                    'market_country_code' => $context->marketCountryCode,
                ],
            );
            $this->storeAttempt($context, $message, $payloadHash, $receipt);

            return $receipt;
        }

        try {
            $receipt = $adapter->send($message);
        } catch (ChannelProviderUnavailableException|RuntimeException $exception) {
            $receipt = new DeliveryReceipt(
                tenantId: $context->tenantId,
                channel: $context->channel,
                connectionKey: $context->connectionKey,
                idempotencyKey: $message->idempotencyKey,
                providerMessageId: null,
                providerEventId: null,
                deliveryState: 'provider_unavailable',
                costAmount: '0.00',
                costCurrency: 'USD',
                metadata: [
                    'reason' => 'provider_unavailable',
                    'market_country_code' => $context->marketCountryCode,
                ],
            );
        }

        $this->storeAttempt($context, $message, $payloadHash, $receipt);

        return $receipt;
    }

    private function payloadHash(ChannelOutboundMessage $message): string
    {
        return hash('sha256', json_encode([
            'recipient' => $message->recipient,
            'subject' => $message->subject,
            'content' => $message->content,
            'attachments' => $message->attachments,
            'thread' => $message->externalThreadReference,
        ], JSON_THROW_ON_ERROR));
    }

    private function storeAttempt(
        ChannelContext $context,
        ChannelOutboundMessage $message,
        string $payloadHash,
        DeliveryReceipt $receipt,
    ): void {
        $now = now();

        DB::table('channel_delivery_attempts')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $context->tenantId,
            'channel' => $context->channel,
            'connection_key' => $context->connectionKey,
            'idempotency_key' => $message->idempotencyKey,
            'external_thread_reference' => $message->externalThreadReference,
            'recipient' => $message->recipient,
            'provider_message_id' => $receipt->providerMessageId,
            'provider_event_id' => $receipt->providerEventId,
            'delivery_state' => $receipt->deliveryState,
            'payload_hash' => $payloadHash,
            'cost_amount' => $receipt->costAmount,
            'cost_currency' => $receipt->costCurrency,
            'metadata' => json_encode($receipt->metadata, JSON_THROW_ON_ERROR),
            'attempted_at' => $now,
            'completed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function toReceipt(object $existing): DeliveryReceipt
    {
        return new DeliveryReceipt(
            tenantId: (string) $existing->tenant_id,
            channel: (string) $existing->channel,
            connectionKey: (string) $existing->connection_key,
            idempotencyKey: (string) $existing->idempotency_key,
            providerMessageId: $existing->provider_message_id === null ? null : (string) $existing->provider_message_id,
            providerEventId: $existing->provider_event_id === null ? null : (string) $existing->provider_event_id,
            deliveryState: (string) $existing->delivery_state,
            costAmount: number_format((float) $existing->cost_amount, 2, '.', ''),
            costCurrency: (string) $existing->cost_currency,
            metadata: json_decode((string) ($existing->metadata ?? '{}'), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
