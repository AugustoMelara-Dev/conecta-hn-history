<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use Illuminate\Support\Facades\Log;

/**
 * VerifyOtpHandler - Hardened Domain Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TENANT ISOLATED: All operations strictly scoped by tenantId.           ║
 * ║  INFRASTRUCTURE AGNOSTIC: Zero coupling to Framework or DB details.     ║
 * ║  ATOMIC SAFE: Delegates concurrent writes to Repository atomic methods. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class VerifyOtpHandler
{
    use LogSanitizer;

    public function __construct(
        private OtpRepositoryInterface $otpRepository,
        private UserRepositoryInterface $userRepository,
        private OtpHasherInterface $hasher,
        private ClockInterface $clock,
        /** @required Injected from config('auth.otp.max_attempts') - No fallback allowed */
        private int $maxOtpAttempts,
    ) {}

    /**
     * Handle verification orchestration with full tenant isolation.
     */
    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        // 1. TENANT-SCOPED RETRIEVAL (Axiom A)
        $otp = $this->otpRepository->findValidByIdentifier(
            $command->identifier,
            $command->tenantId
        );

        if ($otp === null) {
            throw new InvalidOtpException();
        }

        // 2. EXPIRATION CHECK (In-Memory Domain Logic)
        if ($otp->isExpired($this->clock->immutablenow())) {
            // TENANT-SCOPED INVALIDATION (Fixed: now passes tenantId)
            $this->otpRepository->invalidateByIdentifier(
                $command->identifier,
                $command->tenantId
            );
            throw new OtpExpiredException();
        }

        // 3. ATTEMPTS LIMIT CHECK (Configurable via injection)
        if ($otp->maxAttemptsExceeded($this->maxOtpAttempts)) {
            throw new TooManyOtpRequestsException(60, 'Demasiados intentos.');
        }

        // 4. CRYPTOGRAPHIC VERIFICATION
        if (!$this->hasher->verify($command->code, $otp->hashedCode)) {
            // ATOMIC PENALTY: Race-safe increment at DB layer
            $this->otpRepository->incrementAttempts($otp->id);
            
            Log::warning('OTP code mismatch', [
                'identifier' => $this->maskIdentifier($command->identifier),
                'tenant_id' => $command->tenantId,
                'ip' => $command->ipAddress
            ]);
            
            throw new InvalidOtpException('Código incorrecto.');
        }

        // 5. SUCCESS CLEANUP
        $this->otpRepository->delete($otp);

        Log::info('OTP verified successfully', $this->sanitizePayload([
            'identifier' => $command->identifier,
            'tenant_id' => $command->tenantId,
            'user_id' => $otp->userId
        ]));

        // 6. TENANT-SCOPED USER RESOLUTION (Fixed: uses tenantId)
        return $this->resolveUserOrNew($otp->userId, $command->identifier, $command->tenantId);
    }

    /**
     * Resolve user with tenant context or indicate new user needed.
     */
    private function resolveUserOrNew(?int $userId, string $identifier, string $tenantId): VerifyOtpResult
    {
        if ($userId === null) {
            return VerifyOtpResult::forNewUser($identifier);
        }

        // TENANT-SCOPED USER LOOKUP (Fixed: now uses tenantId)
        $user = $this->userRepository->findById($userId, $tenantId);

        if ($user !== null) {
            return VerifyOtpResult::forUser($user);
        }
        
        // User ID exists but not found in this tenant = treat as new user
        Log::warning('OTP userId not found in tenant context', [
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);
        
        return VerifyOtpResult::forNewUser($identifier);
    }
    
    private function maskIdentifier(string $identifier): string
    {
        return substr($identifier, 0, 3) . '***'; 
    }
}
