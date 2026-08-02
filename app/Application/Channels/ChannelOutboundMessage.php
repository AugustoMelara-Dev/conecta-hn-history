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
        public ?string $providerIdempotencyKey = null,
    ) {
        if (! Str::isUuid($tenantId)
            || trim($channel) === ''
            || trim($connectionKey) === ''
            || trim($idempotencyKey) === ''
            || trim($recipient) === ''
            || trim($content) === '') {
            throw new InvalidArgumentException('Channel outbound message is invalid.');
        }

        if ($providerIdempotencyKey !== null && trim($providerIdempotencyKey) === '') {
            throw new InvalidArgumentException('Provider idempotency key cannot be empty.');
        }
    }

    public function withProviderIdempotencyKey(string $providerIdempotencyKey): self
    {
        return new self(
            tenantId: $this->tenantId,
            channel: $this->channel,
            connectionKey: $this->connectionKey,
            idempotencyKey: $this->idempotencyKey,
            externalThreadReference: $this->externalThreadReference,
            recipient: $this->recipient,
            content: $this->content,
            subject: $this->subject,
            attachments: $this->attachments,
            metadata: $this->metadata,
            providerIdempotencyKey: $providerIdempotencyKey,
        );
    }
}
