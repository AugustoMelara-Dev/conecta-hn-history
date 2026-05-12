<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Panel\CanonicalPanelUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $panelUrl = app(CanonicalPanelUrlResolver::class)->forUser($request->user());

        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended($panelUrl)
                    : Inertia::render('Auth/VerifyEmail', ['status' => session('status')]);
    }
}
