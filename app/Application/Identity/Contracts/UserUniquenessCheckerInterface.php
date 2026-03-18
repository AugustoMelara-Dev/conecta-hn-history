<?php

declare(strict_types=1);

namespace App\Application\Identity\Contracts;

/**
 * UserUniquenessCheckerInterface - Application Port
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PORT: Decouples email uniqueness validation from the ORM.             ║
 * ║  TENANT AWARE: Uniqueness is scoped per-tenant by design.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Consumers: UniqueUser validation rule, Actions Layer.
 * Implementors: EloquentUserUniquenessChecker (Infrastructure).
 */
interface UserUniquenessCheckerInterface
{
    /**
     * Check if an email address is unique within the given tenant scope.
     *
     * @param  string  $email  The email to check.
     * @param  string|null  $exceptUserId  Exclude this user ID (for updates).
     * @param  string|null  $tenantId  Scope uniqueness to this tenant.
     */
    public function isEmailUnique(string $email, ?string $exceptUserId = null, ?string $tenantId = null): bool;
}
