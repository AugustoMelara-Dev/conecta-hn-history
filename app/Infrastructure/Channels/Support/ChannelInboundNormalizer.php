<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Support;

use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\NormalizedInboundMessage;

final class ChannelInboundNormalizer
{
    /**
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public static function normalize(
        string $adapterKey,
        ChannelContext $context,
        ChannelInboundMessage $message,
        array $attachments,
        array $metadata = [],
    ): NormalizedInboundMessage {
        $context->assertInboundLineage($message);

        return new NormalizedInboundMessage(
            adapterKey: $adapterKey,
            tenantId: $context->tenantId,
            channel: $context->channel,
            connectionKey: $context->connectionKey,
            externalMessageId: $message->externalMessageId,
            externalThreadReference: $message->externalThreadReference,
            normalizedIdentity: IdentityNormalizer::normalize($message->identityType, $message->identityValue),
            identityType: $message->identityType,
            content: $message->content,
            attachments: $attachments,
            consentStatus: $message->consentStatus,
            deliveryState: 'delivered',
            providerEventId: $message->webhookEventId ?? $message->externalMessageId,
            costAmount: '0.00',
            costCurrency: $context->currency,
            marketCountryCode: $context->marketCountryCode,
            locale: $context->locale,
            timezone: $context->timezone,
            metadata: [
                'webhook_payload_hash' => $message->webhookPayload === null
                    ? hash('sha256', $message->externalMessageId)
                    : hash('sha256', $message->webhookPayload),
                ...$metadata,
            ],
        );
    }
}
