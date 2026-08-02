<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Email;

use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;
use App\Infrastructure\Channels\Support\AttachmentClassifier;
use App\Infrastructure\Channels\Support\ChannelInboundNormalizer;
use App\Infrastructure\Channels\Support\WebhookAuthenticator;

final class FakeEmailAdapter implements ChannelAdapterInterface
{
    public function __construct(private readonly string $webhookSecret) {}

    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        return ChannelCapabilityDecision::allow('email.fake', $context->providerKey('email.fake'));
    }

    public function receive(ChannelContext $context, ChannelInboundMessage $message): NormalizedInboundMessage
    {
        WebhookAuthenticator::assertSigned($message, $this->webhookSecret);

        return ChannelInboundNormalizer::normalize(
            adapterKey: 'email.fake',
            context: $context,
            message: $message,
            attachments: AttachmentClassifier::classify($message->attachments),
        );
    }

    public function send(ChannelOutboundMessage $message): DeliveryReceipt
    {
        $providerKey = $message->providerIdempotencyKey ?? $message->idempotencyKey;
        $hash = substr(hash('sha256', $providerKey), 0, 16);

        return new DeliveryReceipt(
            tenantId: $message->tenantId,
            channel: $message->channel,
            connectionKey: $message->connectionKey,
            idempotencyKey: $message->idempotencyKey,
            providerMessageId: 'mail-'.$hash,
            providerEventId: 'mail-event-'.$hash,
            deliveryState: 'delivered',
            costAmount: '0.01',
            costCurrency: 'USD',
            providerIdempotencyKey: $providerKey,
        );
    }
}
