<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * Audit Logged Impersonation Exit Controller.
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY CRITICAL: Forensic Audit & Session Isolation                   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  1. Verifies impersonation state before action.                          ║
 * ║  2. Logs 'impersonation_ended' event with Original Admin ID & User ID.   ║
 * ║  3. Regenerates Session ID to prevent fixation attacks.                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero App\Models imports (DIP compliant)
 * - Uses UserRepositoryInterface for identity resolution
 */
final class ImpersonationController extends Controller
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private StatefulGuard $auth,
        private LoggerInterface $logger
    ) {}

    /**
     * Stop impersonating and return to original admin session.
     */
    public function leave(Request $request): RedirectResponse
    {
        // 1. Pre-condition: Check if actually impersonating
        if (! $request->session()->has('impersonator_id')) {
            $this->logger->warning('Security: Attempted to leave impersonation without active session.', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);

            return redirect()->route('home');
        }

        $impersonatorId = (int) $request->session()->get('impersonator_id');
        $impersonatedUserId = $request->user()?->id;

        // 2. Forensic Audit Log
        $this->logger->info('impersonation_ended', [
            'original_admin_id' => $impersonatorId,
            'impersonated_user_id' => $impersonatedUserId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // 3. Clear Impersonation Artifacts
        $request->session()->forget(['impersonator_id', 'impersonator_name', 'password_hash_check']);

        // 4. Restore Original Identity
        $this->auth->logout();

        try {
            // DIP COMPLIANT: Uses repository interface instead of User::findOrFail()
            $admin = $this->userRepository->findById($impersonatorId);

            if (! $admin) {
                throw new \RuntimeException("Admin user with ID {$impersonatorId} not found");
            }

            $this->auth->login($admin);

            // 5. Session Fixation Mitigation (Rotate Session ID)
            $request->session()->regenerate();

            return redirect('/admin');

        } catch (\Throwable $e) {
            // Serious Failure: Admin ID from session invalid/deleted.
            // Fallback to complete logout for safety.
            $this->logger->emergency('Security: Failed to restore admin session after impersonation.', [
                'admin_id' => $impersonatorId,
                'error' => $e->getMessage(),
            ]);

            $this->auth->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['system' => 'Session restoration failed. Please login again.']);
        }
    }
}
