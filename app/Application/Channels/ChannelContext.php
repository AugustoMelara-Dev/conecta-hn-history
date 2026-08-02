<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ChannelContext
{
    /**
     * @param  array<string, mixed>  $marketCapabilities
     * @param  array<string, mixed>  $connection
     */
    public function __construct(
        public string $tenantId,
        public string $channel,
        public string $connectionKey,
        public string $marketCountryCode,
        public string $locale,
        public string $currency,
        public string $timezone,
        public array $marketCapabilities = [],
        public array $connection = [],
    ) {
        if (! Str::isUuid($tenantId) || trim($channel) === '' || trim($connectionKey) === '') {
            throw new InvalidArgumentException('Channel context is invalid.');
        }
    }

    public function supports(string $capability): bool
    {
        return data_get($this->marketCapabilities, $capability) === true;
    }

    public function providerKey(string $fallback): string
    {
        $configured = $this->connection['provider'] ?? null;

        return is_string($configured) && trim($configured) !== '' ? trim($configured) : $fallback;
    }
}
