<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Shared\Contracts\LoggerInterface;
use Illuminate\Support\Facades\Log;

/**
 * LaravelLoggerAdapter - Infrastructure Adapter for Logging
 *
 * Implements the Application's LoggerInterface using Laravel's Log facade.
 * This adapter bridges the framework-agnostic port with Laravel's logging system.
 *
 * Register in ServiceProvider:
 *   $this->app->bind(LoggerInterface::class, LaravelLoggerAdapter::class);
 */
final class LaravelLoggerAdapter implements LoggerInterface
{
    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, $context);
    }
}
