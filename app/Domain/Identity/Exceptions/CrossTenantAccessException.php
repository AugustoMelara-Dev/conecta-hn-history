<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use DomainException;

/**
 * CrossTenantAccessException - IDOR Protection Domain Exception
 *
 * Thrown when a request attempts to access or modify resources
 * belonging to a different tenant than the requesting context.
 */
final class CrossTenantAccessException extends DomainException
{
    public function __construct(
        string $resource = 'resource',
        ?int $requestedId = null,
    ) {
        $message = "Access denied: Cannot modify {$resource} from a different tenant.";

        if ($requestedId !== null) {
            $message .= " Requested ID: {$requestedId}";
        }

        parent::__construct($message);
    }

    /**
     * Create for user deletion attempt.
     */
    public static function forUserDeletion(int $userId): self
    {
        return new self('user', $userId);
    }
}
