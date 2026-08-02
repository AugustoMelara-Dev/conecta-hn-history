<?php

declare(strict_types=1);

namespace App\Application\Channels;

final readonly class ChannelConsentRecord
{
    public function __construct(
        public string $id,
        public string $status,
        public string $purpose,
    ) {}
}
