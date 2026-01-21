<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * LoggerInterface - Framework-Agnostic Logging Port
 *
 * This contract allows the Application Layer to perform logging operations
 * without coupling to Laravel's Log facade or any specific logging implementation.
 * The Infrastructure Layer provides the concrete adapter (LaravelLoggerAdapter).
 *
 * @see \App\Infrastructure\Logging\LaravelLoggerAdapter
 */
interface LoggerInterface
{
    /**
     * Log an informational message.
     *
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a warning message.
     *
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log an error message.
     *
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a debug message.
     *
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log a critical message.
     *
     * @param  array<string, mixed>  $context
     */
    public function critical(string $message, array $context = []): void;
}
