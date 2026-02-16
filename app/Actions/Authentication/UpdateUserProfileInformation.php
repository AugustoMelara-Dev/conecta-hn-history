<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use App\Application\Auth\Commands\UpdateUserProfileCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * UpdateUserProfileInformation - Infrastructure Adapter for Authentication
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GATEWAY ONLY: Validates HTTP input and delegates to Application Layer. ║
 * ║  PURE: No Eloquent logic, no side-effects here.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Moved from Fortify/ to Authentication/ for Screaming Architecture compliance.
 */
final readonly class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  mixed  $user
     * @param  array<string, string>  $input
     */
    public function update($user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        // Note: Photo handling is separate in standard Jetstream via separate controller/action usually.
        // UpdateUserProfileInformation mainly handles name/email.
        // If we need to handle photo here, we would need to upload it first or pass it to command.
        // For strict refactor of existing "Update Profile" flow which usually is name/email:

        $this->commandBus->dispatch(
            new UpdateUserProfileCommand(
                userId: (int) $user->id,
                name: $input['name'],
                email: $input['email'],
            )
        );
    }
}
