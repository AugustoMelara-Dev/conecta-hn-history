<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Infrastructure\Services\S3PresignerService;
use InvalidArgumentException;

/**
 * GenerateUploadUrlHandler - The Authorizer (Phase 1)
 *
 * Negotiates upload permission without touching the file binary.
 */
final readonly class GenerateUploadUrlHandler implements GenerateUploadUrlHandlerInterface
{
    private const MAX_ATTEMPTS = 5; // Strict: 5 per minute (Paranoid)

    private const DECAY_SECONDS = 60;

    public function __construct(
        private S3PresignerService $presigner,
        private UuidGeneratorInterface $uuidGenerator,
        private \App\Infrastructure\Security\ResilientRateLimiter $rateLimiter
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(string $tenantId, string $mimeType, int $sizeBytes): array
    {
        $key = 'billing:sign_upload:'.$tenantId;

        // STRATEGY: FAIL-CLOSED
        // If Redis dies, we BLOCK requests. We prioritize Wallet Protection over Upload Availability.
        return $this->rateLimiter->attempt($key, self::MAX_ATTEMPTS, self::DECAY_SECONDS, function () use ($tenantId, $mimeType, $sizeBytes) {

            // 1. INPUT VALIDATION (Fail Fast)
            if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
                throw new InvalidArgumentException('Invalid text/plain MIME type. Allowed: PDF, JPG, PNG.');
            }

            if ($sizeBytes > 10 * 1024 * 1024) { // 10MB
                throw new InvalidArgumentException('File too large. Max 10MB.');
            }

            // 2. KEY GENERATION (Deterministic & Scoped)
            // Pattern: temp/{tenant_id}/{uuid}.{ext}
            $uuid = $this->uuidGenerator->generate();
            $extension = $this->getExtensionFromMime($mimeType);
            $s3Key = sprintf('temp/%s/%s.%s', $tenantId, $uuid, $extension);

            // 3. SECURE SIGNING
            $signed = $this->presigner->generatePutUrl($s3Key, $mimeType, $sizeBytes);

            return [
                'upload_url' => $signed['url'],
                'key' => $s3Key,
                'headers' => $signed['headers'],
            ];

        }, failOpen: false); // <--- CRITICAL: FAIL-CLOSED
    }

    private function getExtensionFromMime(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new \LogicException("Unknown MIME type: {$mime}"),
        };
    }
}
