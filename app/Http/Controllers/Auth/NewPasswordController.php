<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\UpdateUserPasswordAction;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * NewPasswordController - Thin Gateway for Reset Flow
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: Decoupled Reset Logic                                       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Uses Laravel Password Broker but injects Action for actual update.      ║
 * ║  No Hash::make() in this file.                                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     */
    public function store(Request $request, UpdateUserPasswordAction $action): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Broker handles token validation
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, $action) {
                // Delegate mutation to Action
                $action->execute(
                    user: $user,
                    plainPassword: $request->password,
                    refreshRememberToken: true
                );

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
