<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Secondary registration endpoint.
 *
 * Product policy: registration must go through /registrar-negocio wizard.
 */
final class RegisteredUserController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('tenant.register');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()
            ->route('tenant.register')
            ->with('info', 'Usa el registro guiado de negocio para crear tu cuenta.');
    }
}
