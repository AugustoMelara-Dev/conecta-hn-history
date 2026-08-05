<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionInventoryController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSessionRegistry $sessions,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Profile/Sessions', [
            'sessions' => $this->sessions->forUser($user, $request)->map(
                static fn (object $session): array => [
                    'id' => (string) $session->id,
                    'deviceName' => (string) ($session->device_name ?? 'Dispositivo desconocido'),
                    'lastSeenAt' => (string) $session->last_seen_at,
                    'lastAuthenticatedAt' => (string) $session->last_authenticated_at,
                    'mfaVerifiedAt' => $session->mfa_verified_at === null ? null : (string) $session->mfa_verified_at,
                    'isCurrent' => (bool) $session->is_current,
                ],
            )->values(),
        ]);
    }

    public function destroy(Request $request, string $session): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $this->sessions->revokeOne($user, $session, $request);
        } catch (DomainException) {
            return back()->withErrors(['session' => 'La sesión no está disponible o no puede revocarse desde aquí.']);
        }

        return back()->with('status', 'authenticated-session-revoked');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->sessions->revokeOthers($user, $request, 'user_revoked_other_devices');

        return back()->with('status', 'other-authenticated-sessions-revoked');
    }
}
