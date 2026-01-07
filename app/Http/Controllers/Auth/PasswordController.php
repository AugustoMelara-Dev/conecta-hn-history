<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\UpdateUserPasswordAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * PasswordController - Thin HTTP Gateway for Password Security
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: Implementation Hiding                                       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Controller knows NOTHING about hashing algorithms.                      ║
 * ║  Delegates update logic to UpdateUserPasswordAction.                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request, UpdateUserPasswordAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $action->execute(
            user: $request->user(),
            plainPassword: $validated['password']
        );

        return back()->with('status', 'password-updated');
    }
}
