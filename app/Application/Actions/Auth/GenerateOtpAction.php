<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final readonly class GenerateOtpAction
{
    public function __construct(
        private GenerateOtpHandlerInterface $handler,
        private OtpRepositoryInterface $otpRepository,
    ) {}

    public function execute(
        string $tenantId,
        string $identifier,
        string $ipAddress,
        string $userAgent,
        ?string $idempotencyKey = null
    ): array {
        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'sms';

        $this->handler->handle(
            new GenerateOtpCommand(
                tenantId: $tenantId,
                identifier: $identifier,
                type: $type,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                idempotencyKey: $idempotencyKey ?? hash('sha256', $tenantId.'|'.$identifier.'|'.$ipAddress),
            )
        );

        $otp = $this->otpRepository->findValidByIdentifier($identifier, $tenantId);

        if ($otp !== null) {
            Event::dispatch(OtpGenerated::now(
                otpId: $otp->id,
                userId: $otp->userId,
                tenantId: $tenantId,
            ));
        } else {
            Log::warning('otp_delivery_dispatch_skipped_missing_record', [
                'tenant_id' => $tenantId,
                'identifier_hash' => hash('sha256', $identifier),
                'type' => $type,
            ]);
        }

        return [
            'expires_at' => now()->addMinutes((int) config('auth.otp.expiry_minutes', 10))->toIso8601String(),
            'otp_id' => $otp?->id,
        ];
    }
}
