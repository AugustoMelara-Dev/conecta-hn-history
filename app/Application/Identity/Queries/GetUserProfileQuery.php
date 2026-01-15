<?php

declare(strict_types=1);

namespace App\Application\Identity\Queries;

/**
 * GetUserProfileQuery - Intent of Read (DTO)
 * 
 * Contains only the parameters required to fetch a user profile.
 * Does not contain logic.
 */
final readonly class GetUserProfileQuery
{
    public function __construct(
        public int $userId,
    ) {}
}
