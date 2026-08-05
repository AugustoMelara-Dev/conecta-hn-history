<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Support;

use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\NormalizedInboundMessage;
use App\Application\Channels\WebhookAuthenticityEnvelope;

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

        $reserved = [
            WebhookAuthenticityEnvelope::META_SIGNATURE_VERSION,
            WebhookAuthenticityEnvelope::META_PAYLOAD_HASH,
            WebhookAuthenticityEnvelope::META_CLAIM_HASH,
            WebhookAuthenticityEnvelope::META_REPLAY_PROOF_HASH,
        ];
        $metadata = array_diff_key($metadata, array_fill_keys($reserved, true));
        $protectedMetadata = [
            WebhookAuthenticityEnvelope::META_PAYLOAD_HASH => $message->webhookPayload === null
                ? hash('sha256', $message->externalMessageId)
                : hash('sha256', $message->webhookPayload),
        ];
        if (self::hasSignedInputs($message)) {
            $protectedMetadata = WebhookAuthenticityEnvelope::fromInbound($message)->protectedMetadata();
        }

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
                ...$metadata,
                ...$protectedMetadata,
            ],
        );
    }

    private static function hasSignedInputs(ChannelInboundMessage $message): bool
    {
        return $message->webhookEventId !== null
            && trim($message->webhookEventId) !== ''
            && $message->webhookPayload !== null
            && trim($message->webhookPayload) !== ''
            && $message->webhookSignature !== null
            && trim($message->webhookSignature) !== ''
            && $message->webhookTimestamp !== null
            && trim($message->webhookTimestamp) !== ''
            && $message->webhookReplayKey !== null
            && trim($message->webhookReplayKey) !== '';
    }
}
