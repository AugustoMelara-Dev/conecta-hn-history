<?php

declare(strict_types=1);

namespace App\Application\Channels;

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
    ) {}
}
