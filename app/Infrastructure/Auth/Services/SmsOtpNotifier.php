<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Services;

use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Identity\Entities\UserEntity;
use Illuminate\Support\Facades\Log;

/**
 * SmsOtpNotifier - Infrastructure Implementation
 *
 * Handles the "Dirty" work of connecting to external providers.
 */
final class SmsOtpNotifier implements OtpNotifierInterface
{
    use LogSanitizer;

    public function send(
        ?UserEntity $recipient,
        string $plainTextCode,
        string $identifier,
        string $tenantId,
        int $expiryMinutes
    ): void {
        // En un caso real, aquí se llamaría a Twilio, SNS, etc.
        // Para esta implementación de referencia, simulamos el envío logueando de forma segura.

        // SANITIZACIÓN DOBLE: El número/email se enmascara antes de loguear
        Log::info('Enviando SMS/Email OTP (Simulado)', $this->sanitizePayload([
            'tenant_id' => $tenantId,
            'recipient' => $identifier, // LogSanitizer enmascarará esto
            'expiry' => $expiryMinutes,
            // 'code' => $plainTextCode // JAMÁS SE LOGUEA EL CÓDIGO
        ]));

        // Simulación de latencia de red
        // sleep(1);
    }
}
