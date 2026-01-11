<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum RegistrationSource: string
{
    case Email = 'email';
    case Social = 'social';

    public static function fromProvider(string $providerName): string
    {
        return self::Social->value . ':' . $providerName;
    }
}
