<?php

declare(strict_types=1);

namespace App\Application\Channels\Contracts;

use App\Application\Channels\ChannelCapabilityDecision;
use App\Application\Channels\ChannelContext;
use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\ChannelOutboundMessage;
use App\Application\Channels\DeliveryReceipt;
use App\Application\Channels\NormalizedInboundMessage;

interface ChannelAdapterInterface
{
    public function capability(ChannelContext $context): ChannelCapabilityDecision;

    public function receive(ChannelInboundMessage $message): NormalizedInboundMessage;

    public function send(ChannelOutboundMessage $message): DeliveryReceipt;
}
