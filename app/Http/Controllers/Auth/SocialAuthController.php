<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Actions\Auth\SocialLoginAction;
use App\Application\DTOs\Auth\SocialProviderData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Illuminate\Routing\Redirector;

/**
 * SocialAuthController - Thin HTTP Gateway for OAuth
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: Strict Hexagonal Separation                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Responsibility:                                                         ║
 * ║  1. Handle Socialite HTTP Redirects/Callbacks                            ║
 * ║  2. Hydrate DTO (SocialProviderData)                                     ║
 * ║  3. Invoke Action (SocialLoginAction)                                    ║
 * ║  4. Manage Session State (Auth::login)                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class SocialAuthController extends Controller
{
    public function __construct(
        private StatefulGuard $auth,
        private SocialiteFactory $socialite,
        private Redirector $redirector
    ) {}

    /**
     * Redirect to the OAuth provider.
     */
    public function redirect(string $provider): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->socialite->driver($provider)->redirect();
    }

    /**
     * Handle the OAuth provider callback.
     */
    public function callback(string $provider, SocialLoginAction $action): RedirectResponse
    {
        try {
            // 1. External Infrastructure Call (Socialite)
            $socialUser = $this->socialite->driver($provider)->user();

            // 2. Data Normalization (DTO)
            $data = SocialProviderData::fromSocialite($provider, $socialUser);

            // 3. Domain Logic Execution (Action)
            $user = $action->execute($data);

            // 4. Session Management
            $this->auth->login($user, remember: true);

            return $this->redirector->intended(config('fortify.home'));

        } catch (\Exception $e) {
            // Error Handling (Log & Redirect)
            report($e);

            return $this->redirector->route('login')->withErrors([
                'email' => 'Unable to login with '.ucfirst($provider).'. Please try again.',
            ]);
        }
    }
}
