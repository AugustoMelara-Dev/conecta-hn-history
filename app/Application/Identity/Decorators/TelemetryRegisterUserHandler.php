<?php

declare(strict_types=1);

namespace App\Application\Identity\Decorators;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * TelemetryRegisterUserHandler - The Watcher
 * 
 * Measures precise latency and outcome of the operation.
 * Adheres to "Structured Telemetry" rule.
 */
final readonly class TelemetryRegisterUserHandler implements RegisterUserHandlerInterface
{
    public function __construct(
        private RegisterUserHandlerInterface $inner,
        private LoggerInterface $logger
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        $start = microtime(true);

        try {
            $result = $this->inner->handle($command);

            $duration = (microtime(true) - $start) * 1000;

            $this->logger->info('RegisterUser command executed', [
                'latency_ms' => round($duration, 2),
                'success' => true,
                'email_hash' => md5($command->email), // Privacy Preservation
                'command_id' => $command->idempotencyKey ?? 'N/A',
                'status' => 'success'
            ]);

            return $result;

        } catch (Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;

            $this->logger->error('RegisterUser command failed', [
                'latency_ms' => round($duration, 2),
                'success' => false,
                'email_hash' => md5($command->email),
                'command_id' => $command->idempotencyKey ?? 'N/A',
                'status' => 'failure',
                'exception' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
