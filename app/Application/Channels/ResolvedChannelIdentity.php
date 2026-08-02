<?php

declare(strict_types=1);

namespace App\Application\Channels;

final readonly class ResolvedChannelIdentity
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $channel,
        public string $type,
        public string $normalizedValue,
    ) {}
}
