<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Exceptions;

use DomainException;

/**
 * TenantNotFoundException - Domain Exception for Missing Tenant
 *
 * Decouples from Eloquent's ModelNotFoundException to maintain
 * Domain layer independence from Infrastructure.
 */
final class TenantNotFoundException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withId(string $tenantId): self
    {
        return new self("Tenant not found with ID: {$tenantId}");
    }

    public static function withSlug(string $slug): self
    {
        return new self("Tenant not found with slug: {$slug}");
    }

    public static function withDomain(string $domain): self
    {
        return new self("Tenant not found with domain: {$domain}");
    }
}
