<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;

/**
 * TransactionGenerateOtpHandler
 *
 * Strict Type Decorator for ACID compliance in GenerateOtp pipeline.
 * Implements GenerateOtpHandlerInterface to satisfy Russian Doll typing requirements.
 */
final readonly class TransactionGenerateOtpHandler implements GenerateOtpHandlerInterface
{
    public function __construct(
        private GenerateOtpHandlerInterface $inner,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        $this->transactionManager->transaction(fn () => $this->inner->handle($command));
    }
}
