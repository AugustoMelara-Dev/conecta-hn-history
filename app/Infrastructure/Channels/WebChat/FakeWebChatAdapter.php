<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\WebChat;

use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;
use App\Infrastructure\Channels\Support\AttachmentClassifier;
use App\Infrastructure\Channels\Support\ChannelInboundNormalizer;

final class FakeWebChatAdapter implements ChannelAdapterInterface
{
    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        return ChannelCapabilityDecision::allow('web_chat.fake', $context->providerKey('web_chat.fake'));
    }

    public function receive(ChannelContext $context, ChannelInboundMessage $message): NormalizedInboundMessage
    {
        return ChannelInboundNormalizer::normalize(
            adapterKey: 'web_chat.fake',
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
            providerMessageId: 'web-'.$hash,
            providerEventId: 'web-event-'.$hash,
            deliveryState: 'delivered',
            costAmount: '0.00',
            costCurrency: 'USD',
            providerIdempotencyKey: $providerKey,
        );
    }
}
