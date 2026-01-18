<?php

declare(strict_types=1);

namespace App\Application\Identity\Queries;

/**
 * Class GetUserProfileQuery
 *
 * Immutable DTO for querying a user profile.
 * Enforces strict multi-tenancy requirements via mandatory tenantId.
 */
final readonly class GetUserProfileQuery
{
    public function __construct(
        public string $id,
        public string $tenantId,
    ) {}
}
