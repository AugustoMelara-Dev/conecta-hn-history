<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;



/**
 * TenantAwareAuthenticatable - Unified Identity + Tenancy Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INTERFACE SEGREGATION: Combines Authenticatable (Identity) and         ║
 * ║  TenantAware (Multi-tenancy) into a single assertion target.            ║
 * ║  Eliminates dual-assertion boilerplate in Actions.                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @see App\Application\Shared\Contracts\TenantAware
 * @see App\Actions\Authentication\ResetPasswordAction
 */
interface TenantAwareAuthenticatable extends TenantAware
{
    // Inherits getTenantId(): int|string from TenantAware
    // Explicit auth contract — mirrors Illuminate\Contracts\Auth\Authenticatable
    // without coupling the Application layer to the framework namespace.
    public function getAuthIdentifierName(): string;
    public function getAuthIdentifier(): mixed;
    public function getAuthPasswordName(): string;
    public function getAuthPassword(): string;
    public function getRememberToken(): ?string;
    public function setRememberToken(?string $value): void;
    public function getRememberTokenName(): string;
}
