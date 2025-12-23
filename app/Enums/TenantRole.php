<?php

namespace App\Enums;

/**
 * TenantRole - Strong-Typed Role Enum
 *
 * ARCHITECTURAL PATTERN: Strong Typing via Native PHP 8.1+ Enums
 *
 * Eliminates "magic strings" for role comparison, preventing:
 * - Typos in role assignments ('owmer' instead of 'owner')
 * - Case sensitivity bugs ('Owner' vs 'owner')
 * - Invalid role injection attacks
 *
 * Usage:
 *   $tenantUser->role = TenantRole::OWNER;
 *   if ($tenantUser->role === TenantRole::MANAGER) { ... }
 *
 * Laravel automatically casts this with: 'role' => TenantRole::class
 */
enum TenantRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case EMPLOYEE = 'employee';

    /**
     * Get human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propietario',
            self::MANAGER => 'Gerente',
            self::EMPLOYEE => 'Empleado',
        };
    }

    /**
     * Get badge color for UI rendering.
     */
    public function color(): string
    {
        return match ($this) {
            self::OWNER => 'success',
            self::MANAGER => 'warning',
            self::EMPLOYEE => 'info',
        };
    }

    /**
     * Check if this role has administrative privileges.
     */
    public function isAdmin(): bool
    {
        return in_array($this, [self::OWNER, self::MANAGER]);
    }

    /**
     * Get all roles as options array for Select components.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->toArray();
    }
}
