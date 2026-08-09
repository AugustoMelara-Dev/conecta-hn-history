<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels;

use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\ChannelProviderUnavailableException;
use App\Application\Channels\Contracts\ChannelAdapterInterface;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;

final readonly class UnavailableChannelAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private string $channel,
        private string $providerKey,
        private string $reason = 'adapter_not_configured',
    ) {}

    public function capability(ChannelContext $context): ChannelCapabilityDecision
    {
        return ChannelCapabilityDecision::deny(
            $this->channel.'.unavailable',
            $this->providerKey,
            $this->reason,
        );
    }

    public function receive(ChannelContext $context, ChannelInboundMessage $message): NormalizedInboundMessage
    {
        throw new ChannelProviderUnavailableException($this->reason);
    }

    public function send(ChannelOutboundMessage $message): DeliveryReceipt
    {
        throw new ChannelProviderUnavailableException($this->reason);
    }
}
