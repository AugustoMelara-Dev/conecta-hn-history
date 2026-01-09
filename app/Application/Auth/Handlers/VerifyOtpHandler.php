<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\DTOs\Auth\VerifyOtpResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * VerifyOtpHandler - Pure OTP Verification Logic
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SESSION AGNOSTIC: No Auth::login(), Session::*, or HTTP state.         ║
 * ║  SINGLE RESPONSIBILITY: Validates credentials only.                     ║
 * ║  BURN PROTOCOL: OTP is consumed immediately after verification.         ║
 * ║  BRUTE FORCE PROTECTION: Rate limited with progressive lockout.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * The Handler answers: "Is this code valid and who does it belong to?"
 * The Controller answers: "What do I do with this verified user?"
 */
final class VerifyOtpHandler
{
    use LogSanitizer;

    private const MAX_VERIFICATION_ATTEMPTS = 5;
    private const LOCKOUT_DURATION_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly OtpRepositoryInterface $otpRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly RateLimitServiceInterface $rateLimiter,
    ) {}

    /**
     * Handle OTP verification command.
     *
     * @param VerifyOtpCommand $command
     * @return VerifyOtpResult
     * @throws InvalidOtpException When OTP not found or code doesn't match
     * @throws OtpExpiredException When OTP has expired
     * @throws TooManyOtpRequestsException When brute force lockout active
     */
    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        $rateLimitKey = $this->buildRateLimitKey($command->identifier);

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 0: BRUTE FORCE CHECK (Pre-Flight)
        // ═══════════════════════════════════════════════════════════════════
        if ($this->rateLimiter->checkTooManyAttempts($rateLimitKey, self::MAX_VERIFICATION_ATTEMPTS)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);
            
            Log::warning('OTP verification blocked - too many attempts', [
                'identifier' => $this->maskIdentifier($command->identifier),
                'retry_after_seconds' => $retryAfter,
            ]);
            
            throw new TooManyOtpRequestsException(
                retryAfterSeconds: $retryAfter,
                message: 'Demasiados intentos fallidos. Intenta de nuevo en unos minutos.'
            );
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: Retrieve OTP Entity (via Repository)
        // ═══════════════════════════════════════════════════════════════════
        $otp = $this->otpRepository->findValidByIdentifier($command->identifier);

        if ($otp === null) {
            $this->recordFailedAttempt($rateLimitKey, $command->identifier, $command->ipAddress);
            throw new InvalidOtpException();
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: Validate Expiration
        // ═══════════════════════════════════════════════════════════════════
        if ($otp->isExpired()) {
            $this->otpRepository->invalidateByIdentifier($command->identifier);
            $this->recordFailedAttempt($rateLimitKey, $command->identifier, $command->ipAddress);
            
            Log::info('OTP expired', [
                'identifier' => $this->maskIdentifier($command->identifier),
                'expired_at' => $otp->expiresAt,
            ]);
            throw new OtpExpiredException();
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: Cryptographic Verification
        // ═══════════════════════════════════════════════════════════════════
        if (!Hash::check($command->code, $otp->hashedCode)) {
            $this->recordFailedAttempt($rateLimitKey, $command->identifier, $command->ipAddress);
            throw new InvalidOtpException('Código incorrecto.');
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4: SUCCESS - Clear Rate Limit Counter
        // ═══════════════════════════════════════════════════════════════════
        $this->rateLimiter->clear($rateLimitKey);

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 5: Resolve User via Repository (DECOUPLED)
        // ═══════════════════════════════════════════════════════════════════
        $userEntity = null;
        if ($otp->userId !== null) {
            $user = $this->userRepository->findById($otp->userId);
            if ($user !== null) {
                $userEntity = new UserEntity(
                    id: $user->id,
                    tenantId: $user->tenant_id,
                    email: $user->email,
                    name: $user->name,
                    role: $user->role,
                );
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 6: BURN PROTOCOL - Consume OTP (One-Time Use)
        // ═══════════════════════════════════════════════════════════════════
        $this->otpRepository->invalidateByIdentifier($command->identifier);

        Log::info('OTP verified successfully', $this->sanitizePayload([
            'identifier' => $this->maskIdentifier($command->identifier),
            'user_id' => $otp->userId,
            'ip' => $command->ipAddress,
        ]));

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 7: Return Structured Result
        // ═══════════════════════════════════════════════════════════════════
        if ($userEntity !== null) {
            return VerifyOtpResult::forUser($userEntity);
        }

        // User doesn't exist - registration required
        return VerifyOtpResult::forNewUser($command->identifier);
    }

    /**
     * Record a failed verification attempt.
     */
    private function recordFailedAttempt(string $key, string $identifier, string $ip): void
    {
        $this->rateLimiter->hit($key, self::LOCKOUT_DURATION_SECONDS);
        
        Log::info('OTP verification failed - attempt recorded', [
            'identifier' => $this->maskIdentifier($identifier),
            'ip' => $ip,
        ]);
    }

    /**
     * Build composite rate limit key.
     */
    private function buildRateLimitKey(string $identifier): string
    {
        return "otp_verify:{$identifier}";
    }

    /**
     * Mask identifier for PII-safe logging.
     */
    private function maskIdentifier(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier);
            return substr($parts[0], 0, 2) . '***@' . $parts[1];
        }

        return substr($identifier, 0, 4) . '****' . substr($identifier, -2);
    }
}
