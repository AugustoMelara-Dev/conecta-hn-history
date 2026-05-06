<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
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

        if ($user !== null && $user->tenantId !== $command->tenantId) {
            throw SecurityException::crossTenantAccess($user->id ?? 'unknown', $command->tenantId);
        }

        // ═══════════════════════════════════════════════════════════════════
        // PHASE 2: BUSINESS LOGIC (Pure Domain)
        // ═══════════════════════════════════════════════════════════════════
        $plainCode = $this->entropyGenerator->generateNumeric($this->otpLength);
        $hashedCode = $this->hasher->hash($plainCode);

        // Invalidate previous OTPs for this identifier
        $this->otpRepository->invalidateByIdentifier($command->identifier, $command->tenantId);

        // Create new OTP
        $otpEntity = $this->otpRepository->create(
            userId: $user?->id,
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
            userId: $user?->id,
            tenantId: $command->tenantId,
        );

        // Enriched payload for the Notifier (Relay will pick this up)
        $outboxPayload = array_merge($event->toPayload(), [
            'plain_text_code' => $plainCode, // REQUIRED for delivery
            'identifier' => $command->identifier,
            'expiry_minutes' => $this->otpExpiryMinutes,
        ]);

        $message = new DomainEventMessage(
            eventId: Uuid::uuid7()->toString(),
            type: OtpGenerated::class,
            aggregateId: $otpEntity->id,
            payload: json_encode($outboxPayload),
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
