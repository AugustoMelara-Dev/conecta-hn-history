<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * PasswordController - Thin HTTP Gateway for Password Security
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: Implementation Hiding                                       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Controller knows NOTHING about hashing algorithms.                      ║
 * ║  Delegates update logic to Fortify Contract.                             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(
        Request $request,
        UpdatesUserPasswords $updater,
        AuthenticatedSessionRegistry $sessions,
    ): RedirectResponse {
        $updater->update($request->user(), $request->input());
        /** @var User $user */
        $user = $request->user();
        $sessions->revokeAll($user, 'password_changed');
        $request->session()->regenerate();
        $sessions->start($request, $user);

        return back()->with('status', 'password-updated');
    }
}
