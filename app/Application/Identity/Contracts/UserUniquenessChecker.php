<?php

declare(strict_types=1);

namespace App\Application\Identity\Contracts;

/**
 * UserUniquenessChecker - General Field Uniqueness Port
 *
 * Unlike the narrower UserUniquenessCheckerInterface (email-only),
 * this contract supports checking uniqueness on ANY user field
 * (email, phone, username, etc.) with tenant scoping and exclusion.
 *
 * Designed for microservices extraction: implementations can be
 * swapped from Eloquent to gRPC/API calls without touching the
 * Application layer.
 */
interface UserUniquenessChecker
{
    /**
     * Check if a field value is unique within the user namespace.
     *
     * @param string      $field     The field to check (e.g. 'email', 'phone')
     * @param mixed       $value     The value to check uniqueness for
     * @param string|null $tenantId  Tenant scope (null = global check)
     * @param string|null $excludeId User ID to exclude (for updates)
     */
    public function isUnique(string $field, mixed $value, ?string $tenantId = null, ?string $excludeId = null): bool;
}
