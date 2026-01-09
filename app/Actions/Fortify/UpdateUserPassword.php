<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * UpdateUserPassword - Infrastructure Adapter for Fortify
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY ONLY: Validates HTTP input and delegates to Application Layer. ║
 * ║  PURE: No Eloquent logic, no hashing here.                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {}

    /**
     * Validate and update the user's password.
     *
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function update($user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $this->commandBus->dispatch(
            new UpdateUserPasswordCommand(
                userId: (int) $user->id,
                currentPassword: $input['current_password'],
                newPassword: $input['password'],
                refreshRememberToken: true,
            )
        );
    }
}
