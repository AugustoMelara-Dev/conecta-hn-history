<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\UpdateUserProfileCommand;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * UpdateUserProfileHandler - User Profile Management
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  EMAIL VERIFICATION: Automatically resets if email changes.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UpdateUserProfileHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Handle profile update command.
     *
     * @param UpdateUserProfileCommand $command
     * @return void
     * @throws ModelNotFoundException If user not found
     */
    /**
     * Handle profile update command.
     *
     * @param UpdateUserProfileCommand $command
     * @return void
     * @throws ModelNotFoundException If user not found
     */
    public function handle(UpdateUserProfileCommand $command): void
    {
        // 1. Load User
        $user = $this->userRepository->findById($command->userId);

        if ($user === null) {
            throw new ModelNotFoundException("User not found: {$command->userId}");
        }

        // 2. Track if email is changing
        $emailChanged = $user->email !== $command->email;

        // 3. Mutate Immutable Entity (Wither Pattern)
        $updatedUser = $user->withName($command->name)
                            ->withEmail($command->email);

        // 4. Reset email verification if email changed
        if ($emailChanged) {
            $updatedUser = $updatedUser->withEmailVerified(null);
            // Optionally: Dispatch event here, but out of scope for strict refactor request
        }

        // 5. Persist via Repository
        $this->userRepository->save($updatedUser);

        // 6. Audit log
        Log::info('User profile updated', $this->sanitizePayload([
            'user_id' => $command->userId,
            'email_changed' => $emailChanged,
        ]));
    }
}
