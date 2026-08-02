<?php

declare(strict_types=1);

namespace App\Application\Channels;

final readonly class ChannelCapabilityDecision
{
    public function __construct(
        public string $adapterKey,
        public string $providerKey,
        public bool $allowed,
        public string $reason,
        public array $metadata = [],
    ) {}

    /** @param array<string, mixed> $metadata */
    public static function allow(string $adapterKey, string $providerKey, array $metadata = []): self
    {
        return new self($adapterKey, $providerKey, true, 'available', $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public static function deny(string $adapterKey, string $providerKey, string $reason, array $metadata = []): self
    {
        return new self($adapterKey, $providerKey, false, $reason, $metadata);
    }
}
