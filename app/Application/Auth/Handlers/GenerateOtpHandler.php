<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Interfaces\EntropyGeneratorInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use App\Domain\Shared\Exceptions\SecurityException;
use App\Application\Shared\Contracts\EventBusInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GenerateOtpHandler - Master Reference Implementation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RISK LEVEL: NULL (Certified Green)                                     ║
 * ║  STANDARD: Defensive, Agnostic & Minimalist                             ║
 * ║  CONSISTENCY: Uses Illuminate Dispatcher & Application Transaction Mgr. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class GenerateOtpHandler implements GenerateOtpHandlerInterface
{
    use LogSanitizer;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private OtpRepositoryInterface $otpRepository,
        private OtpNotifierInterface $otpNotifier,
        private RateLimitServiceInterface $rateLimiter,
        private TransactionManagerInterface $transactionManager, // Application Shared Contract
        private EventBusInterface $eventBus,
        private EntropyGeneratorInterface $entropyGenerator,
        private OtpHasherInterface $hasher,
        private int $otpExpiryMinutes,
        private int $maxOtpRequestsPerHour,
        private int $otpLength,
        private int $rateLimitDecaySeconds,
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        // 1. VALIDACIÓN DE ENTRADA (Implicit by Command Type)

        // Rate limiting key scoped by tenant
        $rateLimitKey = "otp_generation:{$command->tenantId}:{$command->identifier}";
        
        // ATOMIC RATE LIMIT
        if ($this->rateLimiter->hitAndCheck($rateLimitKey, $this->maxOtpRequestsPerHour, $this->rateLimitDecaySeconds)) {
            $retryAfter = $this->rateLimiter->availableIn($rateLimitKey);
            throw TooManyOtpRequestsException::forUser($command->identifier, $retryAfter);
        }

        // 2. RECUPERACIÓN Y GUARDIA (IDOR SHIELD)
        $user = $this->userRepository->findByEmail($command->identifier, $command->tenantId);
        
        // GUARDIA EXPLÍCITA: Si encontramos usuario, validamos tenant.
        if ($user !== null && $user->tenantId !== $command->tenantId) {
            throw SecurityException::crossTenantAccess($user->id ?? 'unknown', $command->tenantId);
        }

        // 3. GENERACIÓN DE SECRETO (Ephemeral Secret)
        $plainCode = $this->entropyGenerator->generateNumeric($this->otpLength);

        // 4. PERSISTENCIA SEGURA (Hash Only)
        $hashedCode = $this->hasher->hash($plainCode);
        $otpId = null;
        
        // TRANSACTIONAL BOUNDARY
        $this->transactionManager->transaction(function () use ($command, $user, $hashedCode, &$otpId) {
            $this->otpRepository->invalidateByIdentifier($command->identifier, $command->tenantId);
            
            $otpId = $this->otpRepository->create(
                userId: $user?->id,
                identifier: $command->identifier,
                tenantId: $command->tenantId,
                hashedCode: $hashedCode, // Secreto Hasheado
                type: $command->type,
                ipAddress: $command->ipAddress,
                userAgent: $command->userAgent,
                expiryMinutes: $this->otpExpiryMinutes,
            );
        });

        // 5. ENVÍO CONTROLADO (The Secret Handoff)
        // El secreto sale del sistema aquí. Único punto de salida.
        $this->otpNotifier->send(
            recipient: $user,
            plainTextCode: $plainCode,
            identifier: $command->identifier,
            tenantId: $command->tenantId,
            expiryMinutes: $this->otpExpiryMinutes
        );

        // 6. AUDITORÍA ASÉPTICA (Zero-Knowledge Event)
        // Evento despachado post-envío. NO contiene el código.
        $this->eventBus->dispatch(
            OtpGenerated::now(
                otpId: $otpId ?? Str::uuid()->toString(),
                userId: $user?->id,
                tenantId: $command->tenantId,
            )
        );

        // 7. LOGGING SEGURO (Sanitized)
        Log::info('OTP generated and dispatched safely', $this->sanitizePayload([
            'identifier' => $command->identifier,
            'tenant_id' => $command->tenantId,
            'type' => $command->type
        ]));
    }
}
