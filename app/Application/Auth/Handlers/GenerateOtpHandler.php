<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use Illuminate\Support\Facades\Log;

/**
 * GenerateOtpHandler - Secure OTP Generation Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PIPELINE ARCHITECTURE: Manual DB::transaction removed.                  ║
 * ║  TransactionMiddleware handles ACID atomicity.                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class GenerateOtpHandler
{
    use LogSanitizer;

    private const OTP_EXPIRY_MINUTES = 10;
    private const OTP_LENGTH = 6;
    private const MAX_OTP_REQUESTS_PER_HOUR = 3;
    private const RATE_LIMIT_DECAY_SECONDS = 3600; // 1 hour

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly OtpRepositoryInterface $otpRepository,
        private readonly OtpNotifierInterface $notifier,
        private readonly RateLimitServiceInterface $rateLimiter,
    ) {}

    /**
     * Handle OTP generation command.
     *
     * @param GenerateOtpCommand $command
     * @return void
     * @throws TooManyOtpRequestsException If rate limit exceeded
     */
    public function handle(GenerateOtpCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 0: RATE LIMIT CHECK (Anti-SMS Pumping)
        // ═══════════════════════════════════════════════════════════════════
        // Note: Rate limiting is a read-heavy operation, validation-like.
        // It could arguably be done before the transaction via middleware,
        // but dynamic keys (based on command content) are tricky in middleware.
        $rateLimitKey = $this->buildRateLimitKey($command->identifier);
        
        if ($this->rateLimiter->checkTooManyAttempts($rateLimitKey, self::MAX_OTP_REQUESTS_PER_HOUR)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);
            
            Log::warning('OTP generation rate limit exceeded', [
                'identifier' => $this->maskIdentifier($command->identifier),
                'retry_after_seconds' => $retryAfter,
            ]);
            
            throw TooManyOtpRequestsException::forUser($command->identifier, $retryAfter);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: Resolve User (Optional - OTP can work for new users)
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->userRepository->findByEmail($command->identifier);
        $userName = $user?->name ?? 'Usuario';

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: Generate Cryptographically Secure Code (CSPRNG)
        // ═══════════════════════════════════════════════════════════════════
        $plainCode = $this->generateSecureCode();

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: Atomic Persistence
        // NOTE: Running inside TransactionMiddleware context now.
        // ═══════════════════════════════════════════════════════════════════
        
        // Invalidate all previous OTPs for this identifier
        $this->otpRepository->invalidateByIdentifier($command->identifier);

        // Create new OTP
        $this->otpRepository->create(
            userId: $user?->id,
            identifier: $command->identifier,
            plainCode: $plainCode,
            type: $command->type,
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            expiryMinutes: self::OTP_EXPIRY_MINUTES,
        );

        Log::info('OTP generated', $this->sanitizePayload([
            'identifier' => $this->maskIdentifier($command->identifier),
            'type' => $command->type,
            'ip' => $command->ipAddress,
        ]));

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4: Send Notification (Side Effect)
        // ═══════════════════════════════════════════════════════════════════
        // CRITICAL DEVIATION: In Transactional Outbox Pattern, side effects 
        // like sending email directly (via API) inside transaction are RISKY.
        // If API calls hang, the DB transaction hangs.
        // IDEALLY: We dispatch an event here (OtpGenerated) and the Listener sends email.
        // However, user spec only asked to remove DB::transaction usage.
        // It did not explicitly demand converting this notifier to an Event.
        // BUT, if this notifier queues a job (EmailOtpNotifier usually queues), it's fine.
        // The job will be queued *after* commit if using 'afterCommit' property on Job, 
        // OR if we wrap this in an Outbox Event.
        
        // For now, retaining strictly what was asked: Removing DB::transaction wrapper.
        
        try {
            $this->notifier->send(
                otpCode: $plainCode,
                recipientIdentifier: $command->identifier,
                recipientName: $userName,
                expiryMinutes: self::OTP_EXPIRY_MINUTES,
            );
            
            // Record successful attempt
            $this->rateLimiter->hit($rateLimitKey, self::RATE_LIMIT_DECAY_SECONDS);
            
        } catch (\Throwable $e) {
            // As per original logic: Log error but don't fail transaction for notification issues
            // This implies Notification is "Best Effort" here, OR 
            // if we threw exception, the entire OTP creation would rollback (due to middleware).
            // The try-catch block PREVENTS rollback upon notification failure.
            // This matches the previous behavior.
            
            Log::error('OTP notification dispatch failed', [
                'identifier' => $this->maskIdentifier($command->identifier),
                'error' => $e->getMessage(),
            ]);

            if (app()->environment('local')) {
                Log::info('OTP (DEV - Notification Failed)', ['code' => $plainCode]);
            }
        }
    }

    private function buildRateLimitKey(string $identifier): string
    {
        return "otp_generation:{$identifier}";
    }

    private function generateSecureCode(): string
    {
        $code = random_int(100000, 999999);
        return str_pad((string) $code, self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    private function maskIdentifier(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier);
            return substr($parts[0], 0, 2) . '***@' . $parts[1];
        }
        return substr($identifier, 0, 4) . '****' . substr($identifier, -2);
    }
}
