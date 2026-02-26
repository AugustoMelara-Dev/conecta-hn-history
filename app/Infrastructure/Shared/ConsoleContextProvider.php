<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Application\Shared\Contracts\ClientContextProviderInterface;

/**
 * ConsoleContextProvider
 *
 * Provides context for CLI/Job execution (No HTTP Request).
 */
class ConsoleContextProvider implements ClientContextProviderInterface
{
    public function getClientIdentity(): string
    {
        return '127.0.0.1'; // Localhost for CLI
    }

    public function getUserAgent(): string
    {
        return 'Titanium/Console';
    }
}
