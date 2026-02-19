<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Context;

use App\Application\Shared\Common\Interfaces\ClientContextProviderInterface;

/**
 * CliClientContextProvider - Console Transport Adapter
 *
 * Resolves client identity for CLI commands, Seeders, and Tests.
 */
final readonly class CliClientContextProvider implements ClientContextProviderInterface
{
    public function getClientIdentity(): string
    {
        return 'cli-system-process';
    }

    public function getUserAgent(): string
    {
        return 'cli-console-worker';
    }
}
