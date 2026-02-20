<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Context;

use App\Application\Shared\Contracts\ClientContextProviderInterface;

/**
 * WebClientContextProvider - HTTP Transport Adapter
 *
 * Resolves client identity using the HTTP Request IP address.
 */
final readonly class WebClientContextProvider implements ClientContextProviderInterface
{
    public function getClientIdentity(): string
    {
        return request()->ip() ?? 'unknown-ip';
    }

    public function getUserAgent(): string
    {
        return request()->userAgent() ?? 'unknown-ua';
    }
}
