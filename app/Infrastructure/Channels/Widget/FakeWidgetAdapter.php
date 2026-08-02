<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Widget;

use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;
use App\Infrastructure\Channels\Support\AttachmentClassifier;
use App\Infrastructure\Channels\Support\ChannelInboundNormalizer;

final class FakeWidgetAdapter implements ChannelAdapterInterface
{
    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        return ChannelCapabilityDecision::allow('widget.fake', $context->providerKey('widget.fake'));
    }

    public function receive(ChannelContext $context, ChannelInboundMessage $message): NormalizedInboundMessage
    {
        return ChannelInboundNormalizer::normalize(
            adapterKey: 'widget.fake',
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
            providerMessageId: 'widget-'.$hash,
            providerEventId: 'widget-event-'.$hash,
            deliveryState: 'delivered',
            costAmount: '0.00',
            costCurrency: 'USD',
            providerIdempotencyKey: $providerKey,
            metadata: ['widget' => true],
        );
    }
}
