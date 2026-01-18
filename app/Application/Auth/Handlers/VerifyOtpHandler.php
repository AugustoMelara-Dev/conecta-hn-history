<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Domain\Auth\Events\OtpVerified; // I will ensure this exists or use generic
use App\Domain\Auth\Exceptions\InvalidOtpException;
use App\Domain\Auth\Exceptions\OtpExpiredException;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * VerifyOtpHandler - Hardened Domain Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TENANT ISOLATED: All operations strictly scoped by tenantId.           ║
 * ║  CONSISTENCY: Transactional Outbox & Idempotency Barrier.               ║
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
        private TransactionManagerInterface $transactionManager,
        private OutboxRepositoryInterface $outboxRepository,
        private IdempotencyServiceInterface $idempotencyService,
        private LoggerInterface $logger,
        /** @required Injected from config('auth.otp.max_attempts') - No fallback allowed */
        private int $maxOtpAttempts,
    ) {}

    /**
     * Handle verification orchestration with full tenant isolation.
     */
    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: IDEMPOTENCY BARRIER
        // ═══════════════════════════════════════════════════════════════════
        if ($this->idempotencyService->has($command->idempotencyKey)) {
            $cachedResult = $this->idempotencyService->get($command->idempotencyKey);
            // Reconstitution logic would go here if needed, assuming direct object cache for now
            // or we reconstruct VerifyOtpResult from array.
            // For Safety in this phase, we might need a serializer, but assuming Cache handles object serialization.
            if ($cachedResult instanceof VerifyOtpResult) {
                return $cachedResult;
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: BUSINESS LOGIC
        // ═══════════════════════════════════════════════════════════════════
        $otp = $this->otpRepository->findValidByIdentifier(
            $command->identifier,
            $command->tenantId
        );

        if ($otp === null) {
            throw new InvalidOtpException();
        }

        if ($otp->isExpired($this->clock->immutablenow())) {
            $this->otpRepository->invalidateByIdentifier($command->identifier, $command->tenantId);
            throw new OtpExpiredException();
        }

        if ($otp->maxAttemptsExceeded($this->maxOtpAttempts)) {
            throw new TooManyOtpRequestsException(60, 'Demasiados intentos.');
        }

        if (!$this->hasher->verify($command->code, $otp->hashedCode)) {
            $this->transactionManager->transaction(fn() => $this->otpRepository->incrementAttempts($otp->id));
            
            $this->logger->warning('OTP code mismatch', [
                'identifier' => $this->maskIdentifier($command->identifier),
                'tenant_id' => $command->tenantId,
                'ip' => $command->ipAddress
            ]);
            
            throw new InvalidOtpException('Código incorrecto.');
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: ATOMIC COMMIT & OUTBOX
        // ═══════════════════════════════════════════════════════════════════
        $result = $this->resolveUserOrNew($otp->userId, $command->identifier, $command->tenantId);

        $this->transactionManager->transaction(function () use ($otp, $command, $result) {
            // 1. Delete (Consumption)
            $this->otpRepository->delete($otp);

            // 2. Outbox Persistence
            $event = OtpVerified::now(
                otpIdentifier: $command->identifier,
                userId: $otp->userId,
                tenantId: $command->tenantId
            );

            $message = new DomainEventMessage(
                eventId: $event->eventId,
                type: OtpVerified::class,
                aggregateId: (string) $otp->id,
                payload: json_encode($event->toPayload()),
                occurredAt: $this->clock->now(),
                metadata: [
                    'tenant_id' => $command->tenantId,
                    'causation_id' => $command->idempotencyKey,
                    'ip_address' => $command->ipAddress,
                    'user_id' => $otp->userId
                ]
            );

            $this->outboxRepository->save($message);
            
            $this->logger->info('OTP verified and persisted to Outbox', $this->sanitizePayload([
                'identifier' => $command->identifier,
                'tenant_id' => $command->tenantId,
                'user_id' => $otp->userId
            ]));
        });

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4: IDEMPOTENCY STORE
        // ═══════════════════════════════════════════════════════════════════
        $this->idempotencyService->store($command->idempotencyKey, $result);

        return $result;
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
        $this->logger->warning('OTP userId not found in tenant context', [
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
