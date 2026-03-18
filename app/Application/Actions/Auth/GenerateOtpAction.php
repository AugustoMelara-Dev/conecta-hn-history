<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;

final readonly class GenerateOtpAction
{
    public function __construct(
        private GenerateOtpHandlerInterface $handler
    ) {}

    public function execute(
        string $tenantId,
        string $identifier,
        string $ipAddress,
        string $userAgent,
        ?string $idempotencyKey = null
    ): array {
        $this->handler->handle(
            new GenerateOtpCommand(
                tenantId: $tenantId,
                identifier: $identifier,
                type: filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'sms',
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                idempotencyKey: $idempotencyKey ?? hash('sha256', $tenantId.'|'.$identifier.'|'.$ipAddress),
            )
        );

        return [
            'expires_at' => now()->addMinutes((int) config('auth.otp.expiry_minutes', 10))->toIso8601String(),
        ];
    }
}
