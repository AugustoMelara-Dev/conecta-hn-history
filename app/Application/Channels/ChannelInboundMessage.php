<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ChannelInboundMessage
{
    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    public function __construct(
        public string $tenantId,
        public string $channel,
        public string $connectionKey,
        public string $externalMessageId,
        public ?string $externalThreadReference,
        public string $identityType,
        public string $identityValue,
        public string $content,
        public array $attachments = [],
        public ?string $consentStatus = null,
        public ?string $webhookEventId = null,
        public ?string $webhookPayload = null,
        public ?string $webhookSignature = null,
    ) {
        if (! Str::isUuid($tenantId)
            || trim($channel) === ''
            || trim($connectionKey) === ''
            || trim($externalMessageId) === ''
            || trim($identityType) === ''
            || trim($identityValue) === '') {
            throw new InvalidArgumentException('Channel inbound message is invalid.');
        }
    }
}
