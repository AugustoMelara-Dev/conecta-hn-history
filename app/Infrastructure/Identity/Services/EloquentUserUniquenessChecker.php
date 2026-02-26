<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity\Services;

use App\Application\Identity\Contracts\UserUniquenessCheckerInterface;
use Illuminate\Database\DatabaseManager;

/**
 * EloquentUserUniquenessChecker - Infrastructure Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTER: Implements uniqueness check via direct DB query.             ║
 * ║  TENANT SCOPED: Applies tenant_id WHERE clause when provided.         ║
 * ║  ORM DECOUPLED: Uses DatabaseManager, not Eloquent Model.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class EloquentUserUniquenessChecker implements UserUniquenessCheckerInterface
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    public function isEmailUnique(string $email, ?string $exceptUserId = null, ?string $tenantId = null): bool
    {
        $query = $this->db->table('users')->where('email', $email);

        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return ! $query->exists();
    }
}
