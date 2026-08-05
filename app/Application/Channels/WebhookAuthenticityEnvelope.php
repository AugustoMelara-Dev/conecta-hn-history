<?php

declare(strict_types=1);

namespace App\Application\Channels;

use InvalidArgumentException;

final readonly class WebhookAuthenticityEnvelope
{
    public const VERSION = 'dovra-channel-webhook-v1';

    public const META_SIGNATURE_VERSION = 'webhook_signature_version';

    public const META_PAYLOAD_HASH = 'webhook_payload_hash';

    public const META_CLAIM_HASH = 'webhook_claim_hash';

    public const META_REPLAY_PROOF_HASH = 'webhook_replay_proof_hash';

    public function __construct(
        public string $canonicalBytes,
        public string $payloadHash,
        public string $claimHash,
        public string $replayProofHash,
    ) {}

    public static function fromInbound(ChannelInboundMessage $message): self
    {
        $eventId = self::required($message->webhookEventId);
        $payload = self::required($message->webhookPayload);
        $timestamp = self::required($message->webhookTimestamp);
        $replayKey = self::required($message->webhookReplayKey);
        $payloadHash = hash('sha256', $payload);

        $fields = [
            'version' => self::VERSION,
            'tenant_id' => $message->tenantId,
            'channel' => $message->channel,
            'connection_key' => $message->connectionKey,
            'event_id' => $eventId,
            'timestamp' => $timestamp,
            'replay_key' => $replayKey,
        ];
        $canonicalFields = [
            ...$fields,
            'payload_sha256' => $payloadHash,
        ];
        $canonicalBytes = self::frameFields($canonicalFields);
        $replayProofBytes = self::frameFields($fields);

        return new self(
            canonicalBytes: $canonicalBytes,
            payloadHash: $payloadHash,
            claimHash: hash('sha256', $canonicalBytes),
            replayProofHash: hash('sha256', $replayProofBytes),
        );
    }

    /** @return array<string, string> */
    public function protectedMetadata(): array
    {
        return [
            self::META_SIGNATURE_VERSION => self::VERSION,
            self::META_PAYLOAD_HASH => $this->payloadHash,
            self::META_CLAIM_HASH => $this->claimHash,
            self::META_REPLAY_PROOF_HASH => $this->replayProofHash,
        ];
    }

    public static function authenticatedClaimHash(NormalizedInboundMessage $message): ?string
    {
        $metadata = $message->metadata;
        $signedKeys = [
            self::META_SIGNATURE_VERSION,
            self::META_CLAIM_HASH,
            self::META_REPLAY_PROOF_HASH,
        ];
        $hasSignedMetadata = false;
        foreach ($signedKeys as $key) {
            if (array_key_exists($key, $metadata)) {
                $hasSignedMetadata = true;
                break;
            }
        }
        if (! $hasSignedMetadata) {
            return null;
        }

        $version = $metadata[self::META_SIGNATURE_VERSION] ?? null;
        $payloadHash = $metadata[self::META_PAYLOAD_HASH] ?? null;
        $claimHash = $metadata[self::META_CLAIM_HASH] ?? null;
        $replayProofHash = $metadata[self::META_REPLAY_PROOF_HASH] ?? null;
        if (! is_string($version)
            || $version !== self::VERSION
            || ! self::isSha256Hex($payloadHash)
            || ! self::isSha256Hex($claimHash)
            || ! self::isSha256Hex($replayProofHash)) {
            throw new InvalidArgumentException('Channel webhook authenticity metadata is invalid.');
        }

        return $claimHash;
    }

    private static function required(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            throw new InvalidArgumentException('Channel webhook authenticity is required.');
        }

        return $value;
    }

    /** @param array<string, string> $fields */
    private static function frameFields(array $fields): string
    {
        $bytes = '';
        foreach ($fields as $name => $value) {
            $bytes .= pack('N', strlen($name)).$name.pack('N', strlen($value)).$value;
        }

        return $bytes;
    }

    private static function isSha256Hex(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
    }
}
