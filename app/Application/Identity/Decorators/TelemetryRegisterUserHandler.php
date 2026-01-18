<?php

declare(strict_types=1);

namespace App\Application\Identity\Decorators;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * TelemetryRegisterUserHandler - The Secure Watcher
 *
 * P3 FIX: Changed email hashing from MD5 to SHA-256.
 * MD5 is cryptographically broken and should not be used
 * even for non-security purposes like logging, to maintain
 * consistency with security standards.
 *
 * Measures precise latency and outcome of the operation.
 * Adheres to "Structured Telemetry" and "PII Protection" rules.
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
                // P3 FIX: SHA-256 instead of MD5 for consistency
                'email_hash' => hash('sha256', $command->email),
                'command_id' => $command->idempotencyKey ?? 'N/A',
                'status' => 'success'
            ]);

            return $result;

        } catch (Throwable $e) {
            $duration = (microtime(true) - $start) * 1000;

            $this->logger->error('RegisterUser command failed', [
                'latency_ms' => round($duration, 2),
                'success' => false,
                // P3 FIX: SHA-256 instead of MD5 for consistency
                'email_hash' => hash('sha256', $command->email),
                'command_id' => $command->idempotencyKey ?? 'N/A',
                'status' => 'failure',
                'exception' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
