<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
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
        private Dispatcher $dispatcher,
        private AuthenticatedSessionRegistry $sessions,
    ) {}

    /**
     * Reset the user's forgotten password.
     *
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function reset($user, array $input): void
    {
        DB::transaction(function () use ($user, $input): void {
            $user->updatePassword(Hash::make($input['password']));

            // Password recovery is an account-takeover boundary. Any bearer
            // token copied before recovery must stop working immediately.
            $user->tokens()->delete();
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', (string) $user->getAuthIdentifier())
                ->delete();
        });
        $this->sessions->revokeAll($user, 'password_reset');

        $this->dispatcher->dispatch(new PasswordReset($user));
    }
}
