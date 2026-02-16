<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * ResetPasswordAction - Authentication Password Reset Handler
 *
 * Required by Laravel Fortify for password reset flow.
 * Moved from Fortify/ to Authentication/ for Screaming Architecture compliance.
 *
 * REFACTORED: Delegates password persistence to User::updatePassword()
 * (Domain Encapsulation). Hashing remains here (Application Layer responsibility).
 */
final readonly class ResetPasswordAction implements ResetsUserPasswords
{
    public function __construct(
        private \Illuminate\Contracts\Events\Dispatcher $dispatcher
    ) {}

    /**
     * Reset the user's forgotten password.
     *
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function reset($user, array $input): void
    {
        $user->updatePassword(Hash::make($input['password']));

        $this->dispatcher->dispatch(new PasswordReset($user));
    }
}
