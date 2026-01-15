<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

enum ProviderName: string
{
    case GOOGLE = 'google';
    case FACEBOOK = 'facebook';
    case APPLE = 'apple';
    case GITHUB = 'github';
    case LINKEDIN = 'linkedin';

    public static function has(string $value): bool
    {
        return !is_null(self::tryFrom($value));
    }
}
