<?php

declare(strict_types=1);

namespace App\Application\Channels\Contracts;

use App\Application\Channels\ChannelContext;

interface ChannelAdapterResolverInterface
{
    public function resolve(ChannelContext $context): ChannelAdapterInterface;
}
