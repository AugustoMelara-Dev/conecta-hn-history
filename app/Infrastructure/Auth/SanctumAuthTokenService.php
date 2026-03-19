<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Contracts\Scope\TenantContextInterface;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Exceptions\TenantMismatchException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SanctumAuthTokenService bridges token issuance to the active tenant context.
 */
final class SanctumAuthTokenService implements AuthTokenServiceInterface
{
    public function __construct(
        private readonly TenantContextInterface $tenantContext
    ) {}

    public function createTokenForUser(int|string $userId, string $tokenName = 'api-token'): array
    {
        $currentTenantId = $this->tenantContext->getTenantId();

        if ($currentTenantId === null) {
            throw new TenantMismatchException(
                'Cannot issue token without tenant context',
                contextData: ['user_id' => $userId, 'action' => 'token_creation']
            );
        }

        $ownerId = DB::table('tenants')
            ->where('id', $currentTenantId)
            ->value('user_id');

        $isOwner = $ownerId !== null && (string) $ownerId === (string) $userId;
        $user = $isOwner ? User::query()->find($userId) : null;

        if ($user === null) {
            $existsElsewhere = User::query()->whereKey($userId)->exists();

            if ($existsElsewhere) {
                // Logging must not turn a blocked cross-tenant probe into a runtime failure.
                rescue(static function () use ($userId, $currentTenantId): void {
                    Log::channel('security')->critical('Cross-Tenant Token Issuance Attempt BLOCKED', [
                        'event' => 'TokenCreation',
                        'user_id' => $userId,
                        'requested_tenant_context' => $currentTenantId,
                        'severity' => 'CRITICAL',
                        'action' => 'BLOCKED',
                    ]);
                }, report: false);

                throw TenantMismatchException::forResource('User', $userId, $currentTenantId);
            }

            throw UserNotFoundException::withId((string) $userId);
        }

        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
