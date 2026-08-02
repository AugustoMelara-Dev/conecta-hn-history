<?php

declare(strict_types=1);

namespace App\Application\Channels;

final readonly class ChannelConnection
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $channel,
        public string $connectionKey,
        public string $providerKey,
        public string $status,
    ) {}
}
