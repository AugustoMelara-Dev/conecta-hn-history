<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Application\Common\Interfaces\ClientContextProviderInterface;
use Illuminate\Http\Request;

/**
 * RequestContextProvider
 *
 * Extracts client context context from standard HTTP Request.
 */
class RequestContextProvider implements ClientContextProviderInterface
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function getClientIdentity(): string
    {
        return $this->request->ip() ?? '0.0.0.0';
    }

    public function getUserAgent(): string
    {
        return $this->request->userAgent() ?? 'Unknown';
    }
}
