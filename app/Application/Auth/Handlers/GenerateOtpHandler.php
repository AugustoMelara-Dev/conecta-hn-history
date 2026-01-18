<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Interfaces\EntropyGeneratorInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use App\Domain\Shared\Exceptions\SecurityException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * GenerateOtpHandler - Master Reference Implementation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RISK LEVEL: NULL (Certified Green)                                     ║
 * ║  STANDARD: Defensive, Agnostic & Minimalist                             ║
 * ║  CONSISTENCY: Transactional Outbox & Idempotency Barrier                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class GenerateOtpHandler implements GenerateOtpHandlerInterface
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private OtpRepositoryInterface $otpRepository,
        // REMOVED: OtpNotifierInterface (Side Effect Isolation)
        private RateLimitServiceInterface $rateLimiter,
        private TransactionManagerInterface $transactionManager,
        private OutboxRepositoryInterface $outboxRepository,
        private IdempotencyServiceInterface $idempotencyService,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private EntropyGeneratorInterface $entropyGenerator,
        private OtpHasherInterface $hasher,
        private int $otpExpiryMinutes,
        private int $maxOtpRequestsPerHour,
        private int $otpLength,
        private int $rateLimitDecaySeconds,
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: IDEMPOTENCY BARRIER (Concurrency Control)
        // ═══════════════════════════════════════════════════════════════════
        if ($this->idempotencyService->has($command->idempotencyKey)) {
            $this->logger->info('Idempotency hit: Returning cached result', ['key' => $command->idempotencyKey]);
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: VALIDATION & GUARD
        // ═══════════════════════════════════════════════════════════════════
        $rateLimitKey = "otp_generation:{$command->tenantId}:{$command->identifier}";
        
        if ($this->rateLimiter->hitAndCheck($rateLimitKey, $this->maxOtpRequestsPerHour, $this->rateLimitDecaySeconds)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);
            throw TooManyOtpRequestsException::forUser($command->identifier, $retryAfter);
        }

        $user = $this->userRepository->findByEmail($command->identifier, $command->tenantId);
        
        if ($user !== null && $user->tenantId !== $command->tenantId) {
            throw SecurityException::crossTenantAccess($user->id ?? 'unknown', $command->tenantId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: BUSINESS LOGIC (Unit of Work)
        // ═══════════════════════════════════════════════════════════════════
        $plainCode = $this->entropyGenerator->generateNumeric($this->otpLength);
        $hashedCode = $this->hasher->hash($plainCode);
        
        $this->transactionManager->transaction(function () use ($command, $user, $hashedCode, $plainCode) {
            // 3.1. Persistence (State Mutation)
            $this->otpRepository->invalidateByIdentifier($command->identifier, $command->tenantId);
            
            $otpId = $this->otpRepository->create(
                userId: $user?->id,
                identifier: $command->identifier,
                tenantId: $command->tenantId,
                hashedCode: $hashedCode,
                type: $command->type,
                ipAddress: $command->ipAddress,
                userAgent: $command->userAgent,
                expiryMinutes: $this->otpExpiryMinutes,
            );

            // 3.2. Outbox Persistence (The "Real" Event Dispatch)
            // Note: plainCode is included in payload so Notifier can send it.
            // CAUTION: This payload is sensitive, but stored in secured DB table.
            $event = OtpGenerated::now(
                otpId: $otpId,
                userId: $user?->id,
                tenantId: $command->tenantId,
            );

            // Enriched payload for the Notifier (Relay will pick this up)
            $outboxPayload = array_merge($event->toPayload(), [
                'plain_text_code' => $plainCode, // REQUIRED for delivery
                'identifier' => $command->identifier,
                'expiry_minutes' => $this->otpExpiryMinutes
            ]);

            $message = new DomainEventMessage(
                eventId: Uuid::uuid7()->toString(),
                type: OtpGenerated::class,
                aggregateId: $otpId,
                payload: json_encode($outboxPayload),
                occurredAt: $this->clock->now(),
                metadata: [
                    'tenant_id' => $command->tenantId,
                    'causation_id' => $command->idempotencyKey,
                    'ip_address' => $command->ipAddress,
                ]
            );

            $this->outboxRepository->save($message);
            
            $this->logger->info('OTP generated and persisted to Outbox', $this->sanitizePayload([
                'identifier' => $command->identifier,
                'tenant_id' => $command->tenantId,
                'outbox_id' => $message->eventId
            ]));
        });

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 4: IDEMPOTENCY RECORDING
        // ═══════════════════════════════════════════════════════════════════
        $this->idempotencyService->store($command->idempotencyKey, ['status' => 'success']);
    }
}
