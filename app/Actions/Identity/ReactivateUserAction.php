<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * Application Layer Action
 *
 * Orchestrates the domain logic and handles infrastructure concerns
 * (Transactions, Logging, Persistence).
 *
 * REFACTORED: Strict DI — replaced DB:: and Log:: facades with
 * constructor-injected DatabaseManager and LoggerInterface.
 */
final readonly class ReactivateUserAction
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Execute the action.
     *
     * @param  User  $user  The user to reactivate.
     *
     * @throws \DomainException If user is already active.
     */
    public function handle(User $user): void
    {
        // Wrapper for atomicity (best practice even for single saves)
        $this->database->transaction(function () use ($user) {

            // 1. PURE DOMAIN LOGIC
            // Only mutates memory state. Throws DomainException if invalid.
            $user->reactivate();

            // 2. EXPLICIT PERSISTENCE
            // The "save()" happens here, not in the model.
            $user->save();

            // 3. SIDE EFFECTS (Audit/Log)
            $this->logger->info('User successfully reactivated via Action', ['user_id' => $user->id]);
        });
    }
}
