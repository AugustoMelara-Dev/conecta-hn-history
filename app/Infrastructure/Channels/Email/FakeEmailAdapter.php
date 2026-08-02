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
use App\Infrastructure\Channels\Support\IdentityNormalizer;
use InvalidArgumentException;

final class FakeEmailAdapter implements ChannelAdapterInterface
{
    public function __construct(private readonly string $webhookSecret) {}

    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        return ChannelCapabilityDecision::allow('email.fake', $context->providerKey('email.fake'));
    }

    public function receive(ChannelInboundMessage $message): NormalizedInboundMessage
    {
        if ($message->webhookPayload !== null || $message->webhookSignature !== null) {
            $expected = hash_hmac('sha256', (string) $message->webhookPayload, $this->webhookSecret);
            if (! hash_equals($expected, (string) $message->webhookSignature)) {
                throw new InvalidArgumentException('Channel webhook signature is invalid.');
            }
        }

        return new NormalizedInboundMessage(
            adapterKey: 'email.fake',
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
            providerEventId: $message->webhookEventId ?? $message->externalMessageId,
            costAmount: '0.00',
            costCurrency: 'USD',
            marketCountryCode: 'ZZ',
            locale: 'und',
            timezone: 'UTC',
            metadata: [
                'webhook_payload_hash' => $message->webhookPayload === null
                    ? hash('sha256', $message->externalMessageId)
                    : hash('sha256', $message->webhookPayload),
            ],
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
            providerMessageId: 'mail-'.$hash,
            providerEventId: 'mail-event-'.$hash,
            deliveryState: 'delivered',
            costAmount: '0.01',
            costCurrency: 'USD',
        );
    }
}
