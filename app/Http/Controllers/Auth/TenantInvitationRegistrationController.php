<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\TenantInvitationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Panel\CanonicalPanelUrlResolver;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class TenantInvitationRegistrationController extends Controller
{
    public function __construct(
        private readonly TenantInvitationService $invitations,
        private readonly CanonicalPanelUrlResolver $panelUrls,
        private readonly AuthenticatedSessionRegistry $sessions,
    ) {}

    public function create(Request $request, string $token): Response|RedirectResponse
    {
        try {
            $invitation = $this->invitations->publicPreview($token);
        } catch (DomainException) {
            abort(404);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            return redirect()->route('tenant-invitations.show', ['token' => $token]);
        }

        return Inertia::render('Auth/RegisterTenantInvitation', [
            'invitation' => [
                'tenantName' => $invitation->tenantName,
                'inviterName' => $invitation->inviterName,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expiresAt' => $invitation->expiresAt->format(DATE_ATOM),
            ],
        ])->toResponse($request)->withHeaders([
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $result = DB::transaction(function () use ($token, $validated): array {
                $invitation = $this->invitations->publicPreview($token);
                if (User::query()->where('email', $invitation->email)->lockForUpdate()->exists()) {
                    throw new DomainException('Invitation identity already has an account.');
                }

                $user = User::query()->create([
                    'name' => (string) $validated['name'],
                    'email' => $invitation->email,
                    'password' => Hash::make((string) $validated['password']),
                    'role' => 'user',
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $this->invitations->accept($token, (string) $user->getKey());

                return [$user, $invitation->tenantId];
            });
        } catch (DomainException|QueryException) {
            return back()->withErrors([
                'invitation' => 'La invitación no está disponible o ya corresponde a una cuenta existente.',
            ]);
        }

        /** @var User $user */
        [$user, $tenantId] = $result;
        Auth::login($user);
        $request->session()->regenerate();
        $this->sessions->start($request, $user);
        $tenant = Tenant::query()->findOrFail($tenantId);

        return redirect()
            ->to($this->panelUrls->forTenant($tenant))
            ->with('status', 'tenant-invitation-accepted');
    }
}
