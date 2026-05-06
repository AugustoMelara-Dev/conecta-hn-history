<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use Illuminate\Support\Facades\DB;

/**
 * TransactionGenerateOtpHandler
 *
 * Strict Type Decorator for ACID compliance in GenerateOtp pipeline.
 * Implements GenerateOtpHandlerInterface to satisfy Russian Doll typing requirements.
 */
final readonly class TransactionGenerateOtpHandler implements GenerateOtpHandlerInterface
{
    public function __construct(
        private GenerateOtpHandlerInterface $inner
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        DB::transaction(fn () => $this->inner->handle($command));
    }
}
