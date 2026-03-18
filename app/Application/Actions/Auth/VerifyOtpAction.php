<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;

final readonly class VerifyOtpAction
{
    public function __construct(
        private VerifyOtpHandlerInterface $handler
    ) {}

    public function execute(
        string $tenantId,
        string $identifier,
        string $code,
        string $ipAddress,
        ?string $idempotencyKey = null
    ): array {
        $result = $this->handler->handle(
            new VerifyOtpCommand(
                tenantId: $tenantId,
                identifier: $identifier,
                code: $code,
                ipAddress: $ipAddress,
                idempotencyKey: $idempotencyKey ?? hash('sha256', $tenantId.'|'.$identifier.'|'.$code),
            )
        );

        return [
            'message' => $result->requiresRegistration
                ? 'Verificacion exitosa. Completa tu registro.'
                : 'Autenticacion exitosa.',
            'user' => $result->hasUser()
                ? [
                    'id' => $result->userId,
                    'tenant_id' => $result->tenantId,
                ]
                : null,
            'requires_registration' => $result->requiresRegistration,
            'redirect' => $result->requiresRegistration ? '/register' : '/dashboard',
        ];
    }
}
