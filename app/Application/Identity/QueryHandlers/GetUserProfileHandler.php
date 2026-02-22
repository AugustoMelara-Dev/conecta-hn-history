<?php

declare(strict_types=1);

namespace App\Application\Identity\QueryHandlers;

use App\Application\Identity\Contracts\UserReadProjection;
use App\Application\Identity\Queries\GetUserProfileQuery;
use App\Domain\Identity\Exceptions\UserNotFoundException;

/**
 * Class GetUserProfileHandler
 *
 * Handles the retrieval of user profiles with strict tenant isolation.
 * Uses the UserReadProjection port for CQRS-compliant read access.
 *
 * REFACTORED: Replaced ConnectionInterface with UserReadProjection.
 */
final readonly class GetUserProfileHandler
{
    public function __construct(
        private UserReadProjection $userReadProjection
    ) {}

    public function handle(GetUserProfileQuery $query): object
    {
        try {
            return $this->userReadProjection->getById($query->id, $query->tenantId);
        } catch (\Throwable $e) {
            throw new UserNotFoundException('User not found or access denied.');
        }
    }
}
