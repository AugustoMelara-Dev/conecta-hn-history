<?php

declare(strict_types=1);

namespace App\Application\Auth\Enums;

enum AuthStatus: string
{
    case SUCCESS = 'success';
    case NEW_USER = 'new_user';
    case REQUIRES_2FA = 'requires_2fa';
    case REQUIRES_ONBOARDING = 'requires_onboarding';
}
