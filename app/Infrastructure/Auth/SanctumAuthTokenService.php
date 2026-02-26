<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Contracts\Scope\TenantContextInterface;
use App\Exceptions\TenantMismatchException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * SanctumAuthTokenService - Laravel Sanctum Token Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY: ALL token issuance is tenant-context validated               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  IDOR PROTECTION: User must belong to the requesting tenant context     ║
 * ║  to receive a token. Cross-tenant token requests are blocked + logged.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
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

        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY: EXPLICIT TENANT MEMBERSHIP VERIFICATION
        // ═══════════════════════════════════════════════════════════════════════
        // We MUST verify the user belongs to the current tenant context.
        // OPTIMIZATION: Retrieve user_id directly from DB to avoid Model overhead/scopes.
        $ownerId = \Illuminate\Support\Facades\DB::table('tenants')
            ->where('id', $currentTenantId)
            ->value('user_id');

        // Compare strings (UUIDs)
        $isOwner = $ownerId && (string)$ownerId === (string)$userId;

        if ($isOwner) {
            $user = User::where('id', $userId)->first();
        } else {
            $user = null;
        }

        if ($user === null) {
            // Check if user exists but doesn't belong to this tenant (security probe)
            $existsElsewhere = User::where('id', $userId)->exists();

            if ($existsElsewhere) {
                // SECURITY ALERT: Cross-tenant token issuance attempt
                Log::channel('security')->critical('🚨 Cross-Tenant Token Issuance Attempt BLOCKED', [
                    'event' => 'TokenCreation',
                    'user_id' => $userId,
                    'requested_tenant_context' => $currentTenantId,
                    'severity' => 'CRITICAL',
                    'action' => 'BLOCKED',
                ]);

                // Throw specific security exception
                throw TenantMismatchException::forResource('User', $userId, $currentTenantId);
            }

            // User genuinely doesn't exist
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "User {$userId} not found"
            );
        }

        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
