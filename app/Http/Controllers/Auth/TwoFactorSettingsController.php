<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

final class TwoFactorSettingsController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSessionRegistry $sessions,
    ) {}

    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $pending = filled($user->two_factor_secret) && ! $enabled;

        return Inertia::render('Profile/TwoFactor', [
            'enabled' => $enabled,
            'pendingConfirmation' => $pending,
            'requiredForPlatformAdmin' => $user->isSuperAdmin() && (bool) config('security.admin_mfa_required', false),
            'qrCodeDataUri' => $pending
                ? 'data:image/svg+xml;base64,'.base64_encode($user->twoFactorQrCodeSvg())
                : null,
            'recoveryCodes' => $enabled ? $user->recoveryCodes() : [],
        ]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $enable($user, true);

        return redirect()->route('profile.two-factor.show')
            ->with('success', 'Escanea el codigo y confirma un codigo temporal para terminar.');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $confirm($user, $validated['code']);
        $user->tokens()->delete();
        $this->sessions->revokeOthers($user, $request, 'mfa_enrolled');
        $this->sessions->rotateCurrent($request, $user);

        return redirect()->route('profile.two-factor.show')
            ->with('success', 'La verificacion en dos pasos quedo activada. Guarda tus codigos de recuperacion.');
    }

    public function regenerate(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['two_factor' => 'Activa y confirma el segundo factor primero.']);
        }

        $generate($user);
        $user->tokens()->delete();
        $this->sessions->revokeOthers($user, $request, 'mfa_recovery_codes_rotated');
        $this->sessions->rotateCurrent($request, $user);

        return redirect()->route('profile.two-factor.show')
            ->with('success', 'Los codigos anteriores quedaron invalidados.');
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->isSuperAdmin() && (bool) config('security.admin_mfa_required', false)) {
            throw ValidationException::withMessages([
                'two_factor' => 'El segundo factor es obligatorio para administradores de plataforma.',
            ]);
        }

        $disable($user);
        $user->tokens()->delete();
        $this->sessions->revokeOthers($user, $request, 'mfa_disabled');
        $this->sessions->rotateCurrent($request, $user);

        return redirect()->route('profile.edit')
            ->with('success', 'La verificacion en dos pasos fue desactivada.');
    }
}
