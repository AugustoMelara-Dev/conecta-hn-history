<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case SUPER_ADMIN = 'super_admin';
}
