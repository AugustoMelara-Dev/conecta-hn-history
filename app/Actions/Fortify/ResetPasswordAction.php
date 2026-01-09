<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * ResetPasswordAction - Fortify Password Reset Handler
 *
 * Required by Laravel Fortify for password reset flow.
 */
class ResetPasswordAction implements ResetsUserPasswords
{
    /**
     * Reset the user's forgotten password.
     *
     * @param mixed $user
     * @param array<string, string> $input
     */
    public function reset($user, array $input): void
    {
        $user->forceFill([
            'password' => Hash::make($input['password']),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));
    }
}
