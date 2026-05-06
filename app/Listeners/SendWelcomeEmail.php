<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Identity\Events\UserRegisteredViaSocial;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Mail\WelcomeUserMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * SendWelcomeEmail - Queued Welcome Email Dispatcher
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FRESH FETCH PATTERN: Never serializes Eloquent Models.                 ║
 * ║  TENANT ISOLATED: Re-fetches user within tenant scope.                  ║
 * ║  FAIL-SAFE: Gracefully handles deleted users (no fatal errors).         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SendWelcomeEmail implements ShouldQueue
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Handle the UserRegisteredViaSocial domain event.
     */
    public function handle(UserRegisteredViaSocial $event): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // STEP 1: Extract Primitives from Event (ID-Only Rule)
        // ─────────────────────────────────────────────────────────────────────
        $userId = $event->userId;
        $tenantId = $event->tenantId;

        // ─────────────────────────────────────────────────────────────────────
        // STEP 2: Fresh Fetch User Entity (Rehidratación Segura)
        // ─────────────────────────────────────────────────────────────────────
        $user = $this->userRepository->findById($userId, $tenantId);

        // ─────────────────────────────────────────────────────────────────────
        // STEP 3: Guard - User May Have Been Deleted While Queued
        // ─────────────────────────────────────────────────────────────────────
        if ($user === null) {
            Log::warning('SendWelcomeEmail skipped: User no longer exists', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);

            return; // Fail silently - job completes successfully
        }

        // ─────────────────────────────────────────────────────────────────────
        // STEP 4: Send Email Using Fresh Data
        // ─────────────────────────────────────────────────────────────────────
        Mail::to($user->email())->send(new WelcomeUserMail($user));

        Log::info('Welcome email dispatched', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ]);
    }
}
