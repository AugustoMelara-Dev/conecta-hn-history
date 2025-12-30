<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumEnhancements;

/**
 * TenantRole - Rich Domain Enum for Multi-Tenant Authorization
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  BEHAVIORAL OBJECT: Role-Based Access Control (RBAC)                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  Instead of:                                                            ║
 * ║    if ($user->role === 'owner' || $user->role === 'manager') { ... }    ║
 * ║                                                                          ║
 * ║  Use:                                                                   ║
 * ║    if ($user->role->canManageUsers()) { ... }                           ║
 * ║                                                                          ║
 * ║  PERMISSION HIERARCHY:                                                  ║
 * ║    OWNER > MANAGER > EMPLOYEE                                           ║
 * ║                                                                          ║
 * ║  ENCAPSULATED PERMISSIONS:                                              ║
 * ║    - canManageUsers(): Invite/remove team members                       ║
 * ║    - canManageProducts(): CRUD products                                 ║
 * ║    - canViewReports(): Access analytics                                 ║
 * ║    - canManageBilling(): Subscription/payment settings                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum TenantRole: string
{
    use EnumEnhancements;

    case OWNER = 'owner';
    case MANAGER = 'manager';
    case EMPLOYEE = 'employee';
    case VIEWER = 'viewer';

    // ═══════════════════════════════════════════════════════════════════════
    // PRESENTATION METHODS (UI)
    // ═══════════════════════════════════════════════════════════════════════

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Propietario',
            self::MANAGER => 'Gerente',
            self::EMPLOYEE => 'Empleado',
            self::VIEWER => 'Visor',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OWNER => 'success',
            self::MANAGER => 'warning',
            self::EMPLOYEE => 'info',
            self::VIEWER => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OWNER => 'heroicon-o-shield-check',
            self::MANAGER => 'heroicon-o-briefcase',
            self::EMPLOYEE => 'heroicon-o-user',
            self::VIEWER => 'heroicon-o-eye',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OWNER => 'Control total sobre la tienda y facturación',
            self::MANAGER => 'Gestión de productos, pedidos y equipo',
            self::EMPLOYEE => 'Gestión de pedidos y atención al cliente',
            self::VIEWER => 'Solo lectura de datos',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PERMISSION METHODS (Business Logic)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Can this role manage team members?
     */
    public function canManageUsers(): bool
    {
        return match ($this) {
            self::OWNER, self::MANAGER => true,
            default => false,
        };
    }

    /**
     * Can this role manage products (CRUD)?
     */
    public function canManageProducts(): bool
    {
        return match ($this) {
            self::OWNER, self::MANAGER, self::EMPLOYEE => true,
            self::VIEWER => false,
        };
    }

    /**
     * Can this role process orders?
     */
    public function canManageOrders(): bool
    {
        return match ($this) {
            self::OWNER, self::MANAGER, self::EMPLOYEE => true,
            self::VIEWER => false,
        };
    }

    /**
     * Can this role view reports/analytics?
     */
    public function canViewReports(): bool
    {
        return match ($this) {
            self::OWNER, self::MANAGER => true,
            default => false,
        };
    }

    /**
     * Can this role manage billing/subscription?
     */
    public function canManageBilling(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Can this role change store settings?
     */
    public function canManageSettings(): bool
    {
        return match ($this) {
            self::OWNER, self::MANAGER => true,
            default => false,
        };
    }

    /**
     * Can this role delete the store?
     */
    public function canDeleteTenant(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Is this an administrative role?
     */
    public function isAdmin(): bool
    {
        return match ($this) {
            self::OWNER, self::MANAGER => true,
            default => false,
        };
    }

    /**
     * Get the permission level (higher = more power).
     */
    public function level(): int
    {
        return match ($this) {
            self::OWNER => 100,
            self::MANAGER => 75,
            self::EMPLOYEE => 50,
            self::VIEWER => 25,
        };
    }

    /**
     * Can this role manage users with the given role?
     */
    public function canManageRole(self $targetRole): bool
    {
        return $this->level() > $targetRole->level();
    }

    /**
     * Get all permissions as array.
     */
    public function permissions(): array
    {
        return [
            'manage_users' => $this->canManageUsers(),
            'manage_products' => $this->canManageProducts(),
            'manage_orders' => $this->canManageOrders(),
            'view_reports' => $this->canViewReports(),
            'manage_billing' => $this->canManageBilling(),
            'manage_settings' => $this->canManageSettings(),
            'delete_tenant' => $this->canDeleteTenant(),
        ];
    }
}
