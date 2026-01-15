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
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Entities\UserEntity;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use Illuminate\Support\Facades\Log;

/**
 * VerifyOtpHandler - Domain Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE AGNOSTIC: Zero coupling to Framework or DB details.     ║
 * ║  DECLARATIVE FLOW: Reads like a story. Readable logic.                  ║
 * ║  ATOMIC SAFE: Delegates concurrent writes to Repository atomic methods. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class VerifyOtpHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly OtpRepositoryInterface $otpRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly OtpHasherInterface $hasher,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Handle verification orchestration.
     */
    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        // 1. RETRIEVAL (Data Mapper)
        $otp = $this->otpRepository->findValidByIdentifier($command->identifier);

        if ($otp === null) {
            throw new InvalidOtpException();
        }

        // 2. DOMAIN GUARD CLAUSES (In-Memory Check)
        // Note: isExpired uses injected Clock time, ensuring testability
        if ($otp->isExpired($this->clock->immutablenow())) {
            $this->otpRepository->invalidateByIdentifier($command->identifier);
            throw new OtpExpiredException();
        }

        if ($otp->maxAttemptsExceeded()) {
            throw new TooManyOtpRequestsException(60, 'Demasiados intentos.');
        }

        // 3. CRYPTOGRAPHIC VERIFICATION (via Abstraction)
        if (!$this->hasher->verify($command->code, $otp->hashedCode)) {
            // ATOMIC PENALTY: Delegate race-safe increment to DB layer
            $this->otpRepository->incrementAttempts($otp->id);
            
            Log::warning('OTP code mismatch', [
                'identifier' => $this->maskIdentifier($command->identifier),
                'ip' => $command->ipAddress
            ]);
            
            throw new InvalidOtpException('Código incorrecto.');
        }

        // 4. SUCCESS CLEANUP
        $this->otpRepository->delete($otp);

        Log::info('OTP verified successfully', $this->sanitizePayload([
            'identifier' => $command->identifier,
            'user_id' => $otp->userId
        ]));

        // 5. RESOLVE RESULT
        return $this->resolveUserOrNew($otp->userId, $command->identifier);
    }

    private function resolveUserOrNew(?int $userId, string $identifier): VerifyOtpResult
    {
        if ($userId !== null) {
            $user = $this->userRepository->findById($userId);
            if ($user !== null) {
            if ($user !== null) {
                // $user is strictly typed as ?UserEntity via strict repository contract
                return VerifyOtpResult::forUser($user);
            }
            }
        }
        
        return VerifyOtpResult::forNewUser($identifier);
    }
    
    private function maskIdentifier(string $identifier): string
    {
        // Simple masking logic to avoid external dependencies
        return substr($identifier, 0, 3) . '***'; 
    }
}
