<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\WhatsApp;

use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\ChannelProviderUnavailableException;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;
use App\Infrastructure\Channels\Support\AttachmentClassifier;
use App\Infrastructure\Channels\Support\ChannelInboundNormalizer;
use App\Infrastructure\Channels\Support\WebhookAuthenticator;

final class FailClosedWhatsAppAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly bool $credentialsConfigured = false,
        private readonly ?WhatsAppProviderInterface $provider = null,
        private readonly ?string $webhookSecret = null,
    ) {}

    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        $providerKey = $context->providerKey('whatsapp.fail_closed');
        if (! $context->supports('channels.whatsapp')) {
            return ChannelCapabilityDecision::deny('whatsapp.fail_closed', $providerKey, 'market_capability_missing');
        }
        if (! $this->credentialsConfigured || empty($context->connection['secret_reference_key'])) {
            return ChannelCapabilityDecision::deny('whatsapp.fail_closed', $providerKey, 'credentials_missing');
        }
        if ($this->provider === null) {
            return ChannelCapabilityDecision::deny('whatsapp.fail_closed', $providerKey, 'provider_adapter_missing');
        }

        return ChannelCapabilityDecision::allow('whatsapp.fail_closed', $providerKey);
    }

    public function receive(ChannelContext $context, ChannelInboundMessage $message): NormalizedInboundMessage
    {
        WebhookAuthenticator::assertSigned($message, $this->webhookSecret);

        return ChannelInboundNormalizer::normalize(
            adapterKey: 'whatsapp.fail_closed',
            context: $context,
            message: $message,
            attachments: AttachmentClassifier::classify($message->attachments),
        );
    }

    public function send(ChannelOutboundMessage $message): DeliveryReceipt
    {
        if ($this->provider === null) {
            throw new ChannelProviderUnavailableException('provider_unavailable');
        }

        $response = $this->provider->send([
            'recipient' => $message->recipient,
            'content' => $message->content,
            'provider_idempotency_key' => $message->providerIdempotencyKey ?? $message->idempotencyKey,
        ]);

        $providerKey = $message->providerIdempotencyKey ?? $message->idempotencyKey;

        return new DeliveryReceipt(
            tenantId: $message->tenantId,
            channel: $message->channel,
            connectionKey: $message->connectionKey,
            idempotencyKey: $message->idempotencyKey,
            providerMessageId: (string) ($response['provider_message_id'] ?? ''),
            providerEventId: (string) ($response['provider_event_id'] ?? ''),
            deliveryState: (string) ($response['delivery_state'] ?? 'delivered'),
            costAmount: number_format((float) ($response['cost_amount'] ?? 0), 2, '.', ''),
            costCurrency: (string) ($response['cost_currency'] ?? 'USD'),
            providerIdempotencyKey: $providerKey,
            metadata: is_array($response['metadata'] ?? null) ? $response['metadata'] : [],
        );
    }
}
