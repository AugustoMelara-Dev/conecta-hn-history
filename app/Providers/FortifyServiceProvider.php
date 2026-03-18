<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Identity\Fortify\Actions\Authentication\ResetPasswordAction;
use App\Infrastructure\Identity\Fortify\Actions\Authentication\UpdateUserPassword;
use App\Infrastructure\Identity\Fortify\Actions\Identity\CreateNewUser;
use App\Infrastructure\Identity\Fortify\Actions\Identity\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY ACTIONS: Infrastructure Adapters -> Application Layer
        // ═══════════════════════════════════════════════════════════════════════
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::resetUserPasswordsUsing(ResetPasswordAction::class);

        // Rate Limiters
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Views (if needed, but actions are the priority)
        // Checks if views exist or basic registration
        Fortify::ignoreRoutes();

        if (config('fortify.views', true)) {
            /** @var view-string $registerView */
            $registerView = 'auth.register';
            /** @var view-string $loginView */
            $loginView = 'auth.login';
            /** @var view-string $verifyEmailView */
            $verifyEmailView = 'auth.verify-email';
            /** @var view-string $confirmPasswordView */
            $confirmPasswordView = 'auth.confirm-password';
            /** @var view-string $twoFactorView */
            $twoFactorView = 'auth.two-factor-challenge';
            /** @var view-string $forgotPasswordView */
            $forgotPasswordView = 'auth.forgot-password';
            /** @var view-string $resetPasswordView */
            $resetPasswordView = 'auth.reset-password';

            Fortify::registerView(fn (): View => $this->renderAuthView($registerView));
            Fortify::loginView(fn (): View => $this->renderAuthView($loginView));
            Fortify::verifyEmailView(fn (): View => $this->renderAuthView($verifyEmailView));
            Fortify::confirmPasswordView(fn (): View => $this->renderAuthView($confirmPasswordView));
            Fortify::twoFactorChallengeView(fn (): View => $this->renderAuthView($twoFactorView));
            Fortify::requestPasswordResetLinkView(fn (): View => $this->renderAuthView($forgotPasswordView));
            Fortify::resetPasswordView(
                fn ($request): View => $this->renderAuthView($resetPasswordView, ['request' => $request])
            );
        }
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    private function renderAuthView(string $view, array $data = []): View
    {
        return view($view, $data);
    }
}
