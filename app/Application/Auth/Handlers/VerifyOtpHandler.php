<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Domain\Auth\Events\OtpVerified;
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;

/**
 * VerifyOtpHandler - Atomic Protocol Edition
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  ATOMIC PROTOCOL: "Insert-or-Die" Gatekeeper                            â•‘
 * â•‘  CONSISTENCY: Transactional Outbox within Atomic Lock                   â•‘
 * â•‘  PURITY: No side effects (Events/Mailers) outside Outbox                â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class VerifyOtpHandler implements VerifyOtpHandlerInterface
{
    public function __construct(
        private OtpRepositoryInterface $otpRepository,
        private UserRepositoryInterface $userRepository,
        private OutboxRepositoryInterface $outboxRepository,
        private OtpHasherInterface $hasher,
        private ClockInterface $clock,
        /** @required Injected from config('auth.otp.max_attempts') */
        private int $maxOtpAttempts,
    ) {}

    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        // 1. Domain Validation
        $otp = $this->otpRepository->findValidByIdentifier(
            $command->identifier,
            $command->tenantId
        );

        if ($otp === null) {
            throw new InvalidOtpException;
        }

        if ($otp->isExpired($this->clock->now())) {
            $this->otpRepository->invalidateByIdentifier($command->identifier, $command->tenantId);
            throw new OtpExpiredException;
        }

        // REMOVED LEGACY CHECK: if ($otp->maxAttemptsExceeded($this->maxOtpAttempts)) ...
        // Reason: Race Condition Vulnerability. We rely on Atomic DB increment below.

        if (! $this->hasher->verify($command->code, $otp->hashedCode)) {
            // ATOMIC INCREMENT & GATEKEEPER
            // If the limit is reached during this transaction, this will throw MaxOtpAttemptsExceededException
            // and stop the flow immediately.
            $this->otpRepository->incrementAttemptsAtomically((string) $otp->id, $this->maxOtpAttempts);

            throw new InvalidOtpException('CÃ³digo incorrecto.');
        }

        // 2. Consume OTP (Effect)
        $this->otpRepository->delete($otp);

        // 3. Resolve Result
        $result = $this->resolveUserOrNew($otp->userId, $command->identifier, $command->tenantId);

        // 4. Persist Outbox Event
        $event = OtpVerified::now(
            otpIdentifier: $command->identifier,
            userId: $otp->userId,
            tenantId: $command->tenantId
        );

        $this->outboxRepository->append($event);

        return $result;
    }

    private function resolveUserOrNew(?string $userId, string $identifier, string $tenantId): VerifyOtpResult
    {
        if ($userId === null) {
            return VerifyOtpResult::forNewUser($identifier);
        }

        $user = $this->userRepository->findById($userId, $tenantId);

        if ($user !== null) {
            return VerifyOtpResult::forUser($user);
        }

        return VerifyOtpResult::forNewUser($identifier);
    }
}
