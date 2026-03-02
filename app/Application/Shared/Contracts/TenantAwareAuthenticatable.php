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
    // NOTE: No return types — Illuminate\Auth\Authenticatable trait declares all
    // these methods without type hints. Adding types would cause fatal PHP
    // declaration incompatibility on every Eloquent User implementation.
    public function getAuthIdentifierName();
    public function getAuthIdentifier();
    public function getAuthPasswordName();
    public function getAuthPassword();
    public function getRememberToken();
    public function setRememberToken($value);
    public function getRememberTokenName();
}
