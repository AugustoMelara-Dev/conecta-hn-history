<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * UpdateUserPasswordHandler - Secure Password Management
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CENTRALIZED HASHING: Ensures consistent algorithm application-wide.    ║
 * ║  PII SAFE: Never logs plain or hashed passwords.                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UpdateUserPasswordHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Handle password update command.
     *
     * @param UpdateUserPasswordCommand $command
     * @return void
     * @throws ModelNotFoundException If user not found
     */
    /**
     * Handle password update command.
     *
     * @param UpdateUserPasswordCommand $command
     * @return void
     * @throws ModelNotFoundException If user not found
     * @throws \Illuminate\Validation\ValidationException If current password incorrect
     */
    public function handle(UpdateUserPasswordCommand $command): void
    {
        // 1. Load User
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new ModelNotFoundException("User not found: {$command->userId}");
        }

        // 2. Security Guard: Verify current password
        // Note: We access the hashed password from the entity. 
        // If entity doesn't have it loaded (e.g. projection), this might fail, 
        // but UserEntity from Repo should have it.
        if (! Hash::check($command->currentPassword, $user->password ?? '')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ]);
        }

        // 3. Mutate Immutable Entity (Wither Pattern)
        // Centralized hashing here in the Application Handler
        $updatedUser = $user->withPassword(Hash::make($command->newPassword));

        // 4. Optionally refresh remember token
        if ($command->refreshRememberToken) {
            $updatedUser = $updatedUser->withRememberToken(Str::random(60));
        }

        // 5. Persist via Repository
        $this->userRepository->save($updatedUser);

        // 6. Audit log (NO sensitive data)
        Log::info('User password updated', [
            'user_id' => $command->userId,
            'remember_token_refreshed' => $command->refreshRememberToken,
        ]);
    }
}
