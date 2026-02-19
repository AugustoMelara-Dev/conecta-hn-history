<?php

declare(strict_types=1);

namespace App\Application\Auth\Handlers;

use App\Application\Auth\Commands\ResetUserPasswordCommand;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * ResetUserPasswordHandler - Application Layer Password Reset Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RESPONSIBILITY: Resolve User → Hash → Persist → Rotate Token → Event.║
 * ║  FRAMEWORK AGNOSTIC: Uses Hasher/Dispatcher contracts only.            ║
 * ║  TENANT AWARE: Validates tenant ownership and logs audit trail.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REFACTORED: Handler now resolves User by ID from the Command (string),
 * eliminating Eloquent model transport across architectural boundaries.
 */
final readonly class ResetUserPasswordHandler
{
    public function __construct(
        private Hasher $hasher,
        private Dispatcher $dispatcher,
        private LoggerInterface $logger,
    ) {}

    /**
     * Handle password reset command.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If user not found.
     * @throws \RuntimeException If tenant ownership validation fails.
     */
    public function handle(ResetUserPasswordCommand $command): void
    {
        $user = User::where('id', $command->userId)
            ->where('tenant_id', $command->tenantId)
            ->firstOrFail();

        $user->forceFill([
            'password' => $this->hasher->make($command->password),
            'remember_token' => Str::random(60),
        ])->save();

        $this->dispatcher->dispatch(new PasswordReset($user));

        $this->logger->info('Password reset completed', [
            'user_id' => $command->userId,
            'tenant_id' => $command->tenantId,
        ]);
    }
}

