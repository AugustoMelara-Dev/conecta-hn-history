<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ChannelOutboundMessage
{
    /**
     * @param  list<array<string, mixed>>  $attachments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $tenantId,
        public string $channel,
        public string $connectionKey,
        public string $idempotencyKey,
        public ?string $externalThreadReference,
        public string $recipient,
        public string $content,
        public ?string $subject = null,
        public array $attachments = [],
        public array $metadata = [],
    ) {
        if (! Str::isUuid($tenantId)
            || trim($channel) === ''
            || trim($connectionKey) === ''
            || trim($idempotencyKey) === ''
            || trim($recipient) === ''
            || trim($content) === '') {
            throw new InvalidArgumentException('Channel outbound message is invalid.');
        }
    }
}
