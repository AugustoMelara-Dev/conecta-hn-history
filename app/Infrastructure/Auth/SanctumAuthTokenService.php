<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Models\User;

/**
 * SanctumAuthTokenService - Laravel Sanctum Token Adapter
 *
 * ONLY CLASS allowed to import User model for token creation.
 */
final class SanctumAuthTokenService implements AuthTokenServiceInterface
{
    public function createTokenForUser(int|string $userId, string $tokenName = 'api-token'): array
    {
        $user = User::findOrFail($userId);
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
