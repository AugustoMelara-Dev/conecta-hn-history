<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Shared\Interfaces\EntropyGeneratorInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * GenerateOtpHandler - Secure OTP Factory
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURE PERSISTENCE: Hashes OTP before storage (HMAC-SHA256).           ║
 * ║  ZERO KNOWLEDGE DB: Database admins cannot read OTP codes.              ║
 * ║  ASYNC DELIVERY: Dispatches plain code ONLY via event to private queue. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class GenerateOtpHandler
{
    use LogSanitizer;

    private const OTP_EXPIRY_MINUTES = 10;
    private const MAX_OTP_REQUESTS_PER_HOUR = 3;
    private const RATE_LIMIT_DECAY_SECONDS = 3600;
    private const OTP_LENGTH = 6;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly OtpRepositoryInterface $otpRepository,
        private readonly RateLimitServiceInterface $rateLimiter,
        private readonly Dispatcher $eventDispatcher,
        private readonly EntropyGeneratorInterface $entropyGenerator,
        private readonly OtpHasherInterface $hasher,
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        $rateLimitKey = "otp_generation:{$command->identifier}";
        
        // 1. RATE LIMIT CHECK
        if ($this->rateLimiter->checkTooManyAttempts($rateLimitKey, self::MAX_OTP_REQUESTS_PER_HOUR)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);
            throw TooManyOtpRequestsException::forUser($command->identifier, $retryAfter);
        }

        // 2. GENERATION
        $user = $this->userRepository->findByEmail($command->identifier);
        $plainCode = $this->entropyGenerator->generateNumeric(self::OTP_LENGTH);

        // 3. SECURITY HARDENING (Hash before save)
        $hashedCode = $this->hasher->hash($plainCode);

        // 4. PERSISTENCE (Clean & Atomic)
        $this->otpRepository->invalidateByIdentifier($command->identifier);
        
        $this->otpRepository->create(
            userId: $user?->id,
            identifier: $command->identifier,
            hashedCode: $hashedCode, // Storing HMAC
            type: $command->type,
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            expiryMinutes: self::OTP_EXPIRY_MINUTES,
        );

        // 5. UPDATE RATE LIMIT
        $this->rateLimiter->hit($rateLimitKey, self::RATE_LIMIT_DECAY_SECONDS);

        // 6. NOTIFICATION (Async Dispatch)
        // Only the ephemeral event carries the plain code
        $this->eventDispatcher->dispatch(new OtpGenerated(
            identifier: $command->identifier,
            code: $plainCode,
            recipientName: $user?->name ?? 'Usuario',
            expiryMinutes: self::OTP_EXPIRY_MINUTES
        ));

        Log::info('OTP generated and dispatched safely', $this->sanitizePayload([
            'identifier' => $command->identifier,
            'type' => $command->type
        ]));
    }
}
