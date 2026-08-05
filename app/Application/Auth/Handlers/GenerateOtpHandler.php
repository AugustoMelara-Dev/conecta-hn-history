<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Shared\Contracts\SensitivePayloadCipherInterface;
use App\Application\Shared\Interfaces\EntropyGeneratorInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\Entities\DomainEventMessage;
use App\Domain\Shared\Exceptions\SecurityException;
use App\Infrastructure\Shared\Telemetry\TraceContext;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * GenerateOtpHandler - Pure Business Logic Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE CORE: No infrastructure concerns (DB transactions, idempotency).  ║
 * ║  SINGLE RESPONSIBILITY: Generate OTP, persist, and record event.        ║
 * ║  DECORATOR-WRAPPED: Idempotency & Validation are applied externally.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class GenerateOtpHandler implements GenerateOtpHandlerInterface
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private OtpRepositoryInterface $otpRepository,
        private OutboxRepositoryInterface $outboxRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private EntropyGeneratorInterface $entropyGenerator,
        private OtpHasherInterface $hasher,
        private SensitivePayloadCipherInterface $payloadCipher,
        private TraceContext $traceContext,
        private \App\Infrastructure\Shared\Context\IdempotencyContext $idempotencyContext,
        private int $otpExpiryMinutes,
        private int $otpLength,
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // PHASE 1: LOAD AGGREGATE (User Context)
        // ═══════════════════════════════════════════════════════════════════
        $user = $this->userRepository->findByEmail($command->identifier, $command->tenantId);

        if ($user !== null && $user->tenantId() !== $command->tenantId) {
            throw SecurityException::crossTenantAccess($user->id(), $command->tenantId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: BUSINESS LOGIC (Pure Domain)
        // ═══════════════════════════════════════════════════════════════════
        $plainCode = $this->entropyGenerator->generateNumeric($this->otpLength);
        $hashedCode = $this->hasher->hash($plainCode);

        // Atomically replace the single OTP slot for tenant + identifier.
        $otpEntity = $this->otpRepository->create(
            userId: $user?->id(),
            identifier: $command->identifier,
            tenantId: $command->tenantId,
            hashedCode: $hashedCode,
            type: $command->type,
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            expiryMinutes: $this->otpExpiryMinutes,
        );

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 3: EVENT PERSISTENCE (Transactional Outbox)
        // ═══════════════════════════════════════════════════════════════════
        $event = OtpGenerated::now(
            otpId: $otpEntity->id,
            userId: $user?->id(),
            tenantId: $command->tenantId,
            encryptedCode: $this->payloadCipher->encrypt($plainCode),
            identifier: $command->identifier,
            recipientName: $user?->name() ?? 'Usuario',
            expiryMinutes: $this->otpExpiryMinutes,
        );

        $message = new DomainEventMessage(
            eventId: Uuid::uuid7()->toString(),
            type: OtpGenerated::class,
            aggregateId: $otpEntity->id,
            payload: json_encode($event->toPayload()),
            occurredAt: $this->clock->now(),
            metadata: [
                'tenant_id' => $command->tenantId,
                'causation_id' => $this->idempotencyContext->getKey(),
                'ip_address' => $command->ipAddress,
                'trace_id' => $this->traceContext->getTraceId(),
            ]
        );

        $this->outboxRepository->save($message);

        $this->logger->info('OTP generated and persisted to Outbox', $this->sanitizePayload([
            'identifier' => $command->identifier,
            'tenant_id' => $command->tenantId,
            'outbox_id' => $message->eventId,
        ]));
        // Idempotency result captured by decorator via closure return
        // No explicit return needed from pure handler
    }
}
