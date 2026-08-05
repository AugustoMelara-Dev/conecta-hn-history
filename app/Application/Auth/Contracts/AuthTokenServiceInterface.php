<?php

declare(strict_types=1);

namespace App\Application\Auth\Contracts;

/**
 * AuthTokenServiceInterface - Port for API Token Generation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Decouple controllers from Eloquent User model for tokens.     ║
 * ║  CLEAN: Controllers never import User model directly.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface AuthTokenServiceInterface
{
    /**
     * Create an API token for a user by their ID.
     *
     * @param  int|string  $userId  User's primary identifier
     * @param  string  $tokenName  Name for the token (e.g., 'api-token')
     * @return array{user: object, token: string} User data and plain text token
     *
     * @throws \RuntimeException If user not found
     */
    public function createTokenForUser(
        int|string $userId,
        string $tokenName = 'api-token',
        ?string $tenantId = null,
    ): array;
}
