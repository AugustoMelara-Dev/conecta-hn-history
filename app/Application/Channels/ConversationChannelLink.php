<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ConversationChannelLink
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $conversationId,
        public string $channel,
        public string $connectionKey,
        public string $normalizedIdentityHash,
        public ?string $externalThreadReference,
        public string $consentPurpose,
        public ?string $consentStatus,
        public ?string $externalMessageId,
        public ?string $providerEventId,
        public string $lineageIdempotencyKey,
        public string $payloadHash,
        public ?string $lastInboundAt = null,
        public ?string $lastOutboundAt = null,
    ) {
        foreach ([$tenantId, $conversationId] as $value) {
            if (! Str::isUuid($value)) {
                throw new InvalidArgumentException('Conversation channel link IDs must be UUIDs.');
            }
        }
        if (trim($id) === ''
            || trim($channel) === ''
            || trim($connectionKey) === ''
            || preg_match('/^[a-f0-9]{64}$/', $normalizedIdentityHash) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $payloadHash) !== 1
            || trim($consentPurpose) === ''
            || trim($lineageIdempotencyKey) === '') {
            throw new InvalidArgumentException('Conversation channel link is invalid.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'conversation_id' => $this->conversationId,
            'channel' => $this->channel,
            'connection_key' => $this->connectionKey,
            'normalized_identity_hash' => $this->normalizedIdentityHash,
            'external_thread_reference' => $this->externalThreadReference,
            'consent_purpose' => $this->consentPurpose,
            'consent_status' => $this->consentStatus,
            'external_message_id' => $this->externalMessageId,
            'provider_event_id' => $this->providerEventId,
            'lineage_idempotency_key' => $this->lineageIdempotencyKey,
            'payload_hash' => $this->payloadHash,
            'last_inbound_at' => $this->lastInboundAt,
            'last_outbound_at' => $this->lastOutboundAt,
        ];
    }
}
