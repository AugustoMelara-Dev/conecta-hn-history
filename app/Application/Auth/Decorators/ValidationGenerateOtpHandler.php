<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Shared\Contracts\ValidationServiceInterface;

/**
 * ValidationDecorator - The Syntactic Gatekeeper
 * 
 * Enforces schema validity BEFORE business logic.
 * Ensures the Core Handler only deals with well-formed data.
 */
final readonly class ValidationGenerateOtpHandler implements GenerateOtpHandlerInterface
{
    public function __construct(
        private GenerateOtpHandlerInterface $inner,
        private ValidationServiceInterface $validator
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        $this->validator->validate([
            'identifier' => $command->identifier,
            'type' => $command->type,
            'tenant_id' => $command->tenantId,
        ], [
            'identifier' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:email,sms,whatsapp'],
            'tenant_id' => ['required', 'uuid'],
        ]);

        $this->inner->handle($command);
    }
}
