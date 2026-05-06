<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * UpdateUserPassword - Infrastructure Adapter for Authentication
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY ONLY: Validates HTTP input and delegates to Application Layer. ║
 * ║  PURE: No Eloquent logic, no hashing here.                              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Moved from Fortify/ to Authentication/ for Screaming Architecture compliance.
 */
final readonly class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
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
        ])->validate();

        $this->commandBus->dispatch(
            new UpdateUserPasswordCommand(
                userId: (string) $user->id,
                requestingTenantId: (string) $user->tenant_id,
                currentPassword: $input['current_password'],
                newPassword: $input['password'],
                refreshRememberToken: true,
                idempotencyKey: (string) Str::uuid(),
            )
        );
    }
}
