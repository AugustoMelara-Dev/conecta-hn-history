<?php

declare(strict_types=1);

namespace App\Application\Common\Interfaces;

/**
 * ClientContextProviderInterface - Protocol Agnostic Identity
 *
 * Abstraction layer to identify the client initiator regardless
 * of the transport protocol (HTTP, CLI, Queue, WebSocket).
 */
interface ClientContextProviderInterface
{
    public function getClientIdentity(): string;

    public function getUserAgent(): string;
}
