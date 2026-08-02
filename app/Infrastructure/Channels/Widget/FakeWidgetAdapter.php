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
use App\Infrastructure\Channels\Support\IdentityNormalizer;

final class FakeWidgetAdapter implements ChannelAdapterInterface
{
    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        return ChannelCapabilityDecision::allow('widget.fake', $context->providerKey('widget.fake'));
    }

    public function receive(ChannelInboundMessage $message): NormalizedInboundMessage
    {
        return new NormalizedInboundMessage(
            adapterKey: 'widget.fake',
            tenantId: $message->tenantId,
            channel: $message->channel,
            externalMessageId: $message->externalMessageId,
            externalThreadReference: $message->externalThreadReference,
            normalizedIdentity: IdentityNormalizer::normalize($message->identityType, $message->identityValue),
            identityType: $message->identityType,
            content: $message->content,
            attachments: AttachmentClassifier::classify($message->attachments),
            consentStatus: $message->consentStatus,
            deliveryState: 'delivered',
            providerEventId: $message->externalMessageId,
            costAmount: '0.00',
            costCurrency: 'USD',
            marketCountryCode: 'ZZ',
            locale: 'und',
            timezone: 'UTC',
            metadata: [],
        );
    }

    public function send(ChannelOutboundMessage $message): DeliveryReceipt
    {
        $hash = substr(hash('sha256', $message->idempotencyKey), 0, 16);

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
            metadata: ['widget' => true],
        );
    }
}
