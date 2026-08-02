<?php

declare(strict_types=1);

namespace App\Application\Ai\Workforce;

enum AgentRole: string
{
    case FRONT_DESK = 'front_desk';
    case REVENUE = 'revenue';
    case SERVICE = 'service';
    case SUPPORT = 'support';
    case OPERATIONS = 'operations';
    case GROWTH = 'growth';
    case MANAGER = 'manager';

    public static function tryFromKey(string $key): ?self
    {
        return self::tryFrom(strtolower(trim($key)));
    }
}
