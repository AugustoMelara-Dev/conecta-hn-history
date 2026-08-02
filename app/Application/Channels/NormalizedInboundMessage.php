<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class NormalizedInboundMessage
{
    /**
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $adapterKey,
        public string $tenantId,
        public string $channel,
        public string $connectionKey,
        public string $externalMessageId,
        public ?string $externalThreadReference,
        public string $normalizedIdentity,
        public string $identityType,
        public string $content,
        public array $attachments,
        public ?string $consentStatus,
        public string $deliveryState,
        public ?string $providerEventId,
        public string $costAmount,
        public string $costCurrency,
        public string $marketCountryCode,
        public string $locale,
        public string $timezone,
        public array $metadata = [],
    ) {
        if (! Str::isUuid($tenantId)
            || trim($adapterKey) === ''
            || trim($channel) === ''
            || trim($connectionKey) === ''
            || trim($externalMessageId) === ''
            || trim($normalizedIdentity) === ''
            || trim($identityType) === ''
            || trim($deliveryState) === '') {
            throw new InvalidArgumentException('Normalized inbound channel message is invalid.');
        }

        ChannelContext::assertValidMarketContext($marketCountryCode, $locale, $costCurrency, $timezone);
    }
}
