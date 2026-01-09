<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Exceptions;

/**
 * TenantDomainException - Domain Invariant Violation Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DOMAIN EXCEPTION: Business Rule Violation                               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This exception is thrown when a domain operation violates a business   ║
 * ║  invariant. Unlike infrastructure exceptions (DB errors, network),      ║
 * ║  domain exceptions represent logical business constraint failures.      ║
 * ║                                                                          ║
 * ║  USAGE:                                                                  ║
 * ║    throw TenantDomainException::cannotEnhanceSuspendedProfile();        ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class TenantDomainException extends \DomainException
{
    public static function cannotEnhanceSuspendedProfile(): self
    {
        return new self(
            'Cannot apply AI enhancement to a suspended tenant profile. '.
            'The tenant must be in active status before enhancements can be applied.'
        );
    }

    public static function cannotEnhanceArchivedProfile(): self
    {
        return new self(
            'Cannot apply AI enhancement to an archived tenant. '.
            'Archived tenants are read-only and cannot be modified.'
        );
    }

    public static function cannotEnhanceWithoutOwner(): self
    {
        return new self(
            'Cannot apply AI enhancement to a tenant without an owner. '.
            'A valid user_id must be set before applying enhancements.'
        );
    }

    public static function invalidEnhancementData(string $field): self
    {
        return new self(
            "Invalid AI enhancement data: required field '{$field}' is missing or empty."
        );
    }

    public static function alreadyEnhanced(): self
    {
        return new self(
            'This tenant profile has already been enhanced by AI. '.
            'Re-enhancement requires explicit override flag.'
        );
    }
}
