<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\LoggerInterface;
use Illuminate\Log\LogManager;

final class LaravelLoggerAdapter implements LoggerInterface
{
    public function __construct(
        private readonly LogManager $logManager
    ) {}

    public function debug(string $message, array $context = []): void
    {
        $this->logManager->channel('jobs')->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logManager->channel('jobs')->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logManager->channel('jobs')->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logManager->channel('jobs')->error($message, $context);
    }
}
