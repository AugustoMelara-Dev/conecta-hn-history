<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

final class MarkTwoFactorSessionVerified
{
    public function handle(ValidTwoFactorAuthenticationCodeProvided $event): void
    {
        session()->put('auth.two_factor_verified', [
            'user_id' => (string) $event->user->getAuthIdentifier(),
            'verified_at' => now()->getTimestamp(),
        ]);
    }
}
