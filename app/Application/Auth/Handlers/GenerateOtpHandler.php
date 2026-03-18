<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Contracts\OtpDeliveryPayloadStoreInterface;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\EntropyGeneratorInterface;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Exceptions\SecurityException;
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $logger,
        private EntropyGeneratorInterface $entropyGenerator,
        private OtpHasherInterface $hasher,
        private OtpDeliveryPayloadStoreInterface $deliveryPayloadStore,
        private DataScrubberInterface $scrubber,
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

        // Invalidate previous OTPs for this identifier
        $this->otpRepository->invalidateByIdentifier($command->identifier, $command->tenantId);

        // Create new OTP
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
        );

        $this->deliveryPayloadStore->put(
            otpId: $otpEntity->id,
            plainTextCode: $plainCode,
            identifier: $command->identifier,
            expiryMinutes: $this->otpExpiryMinutes,
        );

        $this->outboxRepository->append($event);

        $this->logger->info('OTP generated and persisted to Outbox', $this->sanitizePayload([
            'identifier' => $command->identifier,
            'tenant_id' => $command->tenantId,
            'outbox_type' => OtpGenerated::class,
        ], $this->scrubber));
        // Idempotency result captured by decorator via closure return
        // No explicit return needed from pure handler
    }
}
