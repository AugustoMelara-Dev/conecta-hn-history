<?php

declare(strict_types=1);

namespace App\Application\Identity\QueryHandlers;

use App\Application\Identity\Queries\GetUserProfileQuery;
// Assuming this exists or returns array/object
use App\Domain\Identity\Exceptions\UserNotFoundException;
use Illuminate\Database\ConnectionInterface;

/**
 * Class GetUserProfileHandler
 *
 * Handles the retrieval of user profiles with strict tenant isolation.
 * Uses optimized query builder strictly scoped by tenant_id.
 */
final readonly class GetUserProfileHandler
{
    public function __construct(
        private ConnectionInterface $db
    ) {}

    public function handle(GetUserProfileQuery $query): object
    {
        $user = $this->db->table('users')
            ->where('id', '=', $query->id)
            ->where('tenant_id', '=', $query->tenantId) // Tenant Firewall
            ->select(['id', 'name', 'email', 'created_at']) // Explicit columns
            ->first();

        if ($user === null) {
            throw new UserNotFoundException('User not found or access denied.');
        }

        return $user;
    }
}
