<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * NewPasswordController - Thin Gateway for Reset Flow
 */
final class NewPasswordController extends Controller
{
    public function __construct(
        private PasswordBroker $passwordBroker,
        private Redirector $redirector
    ) {}

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
    public function store(Request $request, ResetsUserPasswords $resetter): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Broker handles token validation
        $status = $this->passwordBroker->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, $resetter) {
                $resetter->reset($user, $request->all());
            }
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return $this->redirector->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
