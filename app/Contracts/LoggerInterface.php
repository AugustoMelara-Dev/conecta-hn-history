<?php

declare(strict_types=1);

namespace App\Contracts;

interface LoggerInterface
{
    /**
     * Detailed debug information.
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Interesting events.
     * Example: User logs in, SQL logs.
     */
    public function info(string $message, array $context = []): void;

    /**
     * Normal but significant events.
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function error(string $message, array $context = []): void;
}
