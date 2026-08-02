<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Sms;

use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\ChannelProviderUnavailableException;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;
use App\Infrastructure\Channels\Support\AttachmentClassifier;
use App\Infrastructure\Channels\Support\IdentityNormalizer;

final class FailClosedSmsAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly bool $credentialsConfigured = false,
        private readonly ?SmsProviderInterface $provider = null,
    ) {}

    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        $providerKey = $context->providerKey('sms.fail_closed');
        if (! $context->supports('channels.sms')) {
            return ChannelCapabilityDecision::deny('sms.fail_closed', $providerKey, 'market_capability_missing');
        }
        if (! $this->credentialsConfigured || empty($context->connection['secret_reference_key'])) {
            return ChannelCapabilityDecision::deny('sms.fail_closed', $providerKey, 'credentials_missing');
        }

        return ChannelCapabilityDecision::allow('sms.fail_closed', $providerKey);
    }

    public function receive(ChannelInboundMessage $message): NormalizedInboundMessage
    {
        return new NormalizedInboundMessage(
            adapterKey: 'sms.fail_closed',
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
        ]);

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
            metadata: is_array($response['metadata'] ?? null) ? $response['metadata'] : [],
        );
    }
}
