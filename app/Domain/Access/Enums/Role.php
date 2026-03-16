<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

enum Role: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case OPERATOR = 'operator';
    case VIEWER = 'viewer';
}
