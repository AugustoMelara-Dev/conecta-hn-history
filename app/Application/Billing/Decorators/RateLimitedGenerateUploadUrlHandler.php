<?php

declare(strict_types=1);

namespace App\Application\Billing\Decorators;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Billing\Handlers\GenerateUploadUrlHandlerInterface;
use App\Application\Shared\Contracts\RateLimiterInterface;
use Illuminate\Validation\ValidationException;

/**
 * RateLimitedGenerateUploadUrlHandler - DoS Protection (Level 30)
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  DEFENSE: SIGNING ENDPOINT PROTECTION                                    â•‘
 * â•‘  1. Limits: 20 requests per minute per tenant (Higher burst for uploads).â•‘
 * â•‘  2. Protects: CPU (Signing) & Disk/S3 Metadata Spam.                     â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class RateLimitedGenerateUploadUrlHandler implements GenerateUploadUrlHandlerInterface
{
    private const MAX_ATTEMPTS = 20; // 20 files per minute seems reasonable

    private const DECAY_SECONDS = 60;

    public function __construct(
        private GenerateUploadUrlHandlerInterface $inner,
        private RateLimiterInterface $rateLimiter,
        private LoggerInterface $logger,
    ) {}

    public function handle(string $tenantId, string $mimeType, int $sizeBytes): array
    {
        $key = 'billing:sign_upload:'.$tenantId;

        try {
            if ($this->rateLimiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $seconds = $this->rateLimiter->availableIn($key);

                throw ValidationException::withMessages([
                    'tenant_id' => ["Upload rate limit exceeded. Please wait {$seconds} seconds."],
                ]);
            }

            $this->rateLimiter->hit($key, self::DECAY_SECONDS);
        } catch (ValidationException $e) {
            throw $e; // Re-throw actual validations
        } catch (\Throwable $e) {
            // CIRCUIT BREAKER: Fail Open
            // If Redis/Cache is down, we do NOT want to block payments.
            // We log the critical failure but allow the request to proceed.
            $this->logger->critical('Rate Limiter FAILURE (Fail-Open active): '.$e->getMessage());
        }

        return $this->inner->handle($tenantId, $mimeType, $sizeBytes);
    }
}
