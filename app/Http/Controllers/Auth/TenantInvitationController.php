<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\TenantInvitationService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Panel\CanonicalPanelUrlResolver;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class TenantInvitationController extends Controller
{
    public function __construct(
        private readonly TenantInvitationService $invitations,
        private readonly CanonicalPanelUrlResolver $panelUrls,
    ) {}

    public function show(Request $request, string $token): Response
    {
        try {
            $invitation = $this->invitations->preview($token, (string) $request->user()->getKey());
        } catch (DomainException) {
            abort(404);
        }

        return Inertia::render('Auth/AcceptTenantInvitation', [
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

    public function accept(Request $request, string $token): RedirectResponse
    {
        try {
            $preview = $this->invitations->preview($token, (string) $request->user()->getKey());
            $this->invitations->accept($token, (string) $request->user()->getKey());
        } catch (DomainException) {
            return back()->withErrors([
                'invitation' => 'La invitación no está disponible o no corresponde a esta cuenta.',
            ]);
        }

        $tenant = Tenant::query()->findOrFail($preview->tenantId);

        return redirect()
            ->to($this->panelUrls->forTenant($tenant))
            ->with('status', 'tenant-invitation-accepted');
    }
}
