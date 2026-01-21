<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

/**
 * UserRole - Canonical Domain Enum for User Roles
 *
 * IMPORTANT: String values must match database column values.
 * Legacy compatibility maintained with 'seller' and 'user' values.
 */
enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case TENANT_OWNER = 'seller';    // Legacy: stored as 'seller' in DB
    case STAFF = 'staff';
    case CUSTOMER = 'user';           // Legacy: stored as 'user' in DB
}
