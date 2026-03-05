<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\DTOs\Auth\SocialProviderData;
use App\Domain\Shared\Enums\ProviderName;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

/**
 * SocialAuthController - Thin HTTP Gateway for OAuth
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: Strict Hexagonal Separation                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Responsibility:                                                         ║
 * ║  1. Handle Socialite HTTP Redirects/Callbacks                            ║
 * ║  2. Hydrate Command (LoginViaSocialProviderCommand)                       ║
 * ║  3. Invoke Handler (LoginViaSocialProviderHandler)                        ║
 * ║  4. Bridge: Resolve Authenticatable model, Manage Session State          ║
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
    public function callback(string $provider, LoginViaSocialProviderHandlerInterface $handler): RedirectResponse
    {
        try {
            // 1. External Infrastructure Call (Socialite)
            $socialUser = $this->socialite->driver($provider)->user();

            // 2. Command Hydration — ACL boundary: extract primitives here
            $command = new LoginViaSocialProviderCommand(
                tenantId: (string) config('app.default_tenant_id', 'global'),
                providerName: ProviderName::from(strtolower($provider)),
                providerId: (string) $socialUser->getId(),
                email: (string) $socialUser->getEmail(),
                name: $socialUser->getName() ?? explode('@', (string) $socialUser->getEmail())[0],
                avatarUrl: $socialUser->getAvatar(),
                accessToken: $socialUser->token ?? null,
                refreshToken: $socialUser->refreshToken ?? null,
                idempotencyKey: Str::uuid()->toString(),
            );

            // 3. Domain Logic Execution
            $result = $handler->handle($command);

            // 4. Routing on status
            if ($result->status === \App\Application\Auth\Enums\AuthStatus::REQUIRES_ONBOARDING) {
                return $this->redirector->route('register.onboarding', ['user' => $result->userId]);
            }

            // 5. Infrastructure Bridge — resolve Authenticatable from primitive userId
            $authenticatableUser = User::findOrFail($result->userId);
            $this->auth->login($authenticatableUser, remember: true);

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
