<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Authentication\ResetPasswordAction;
use App\Actions\Authentication\UpdateUserPassword;
use App\Actions\Authentication\UpdateUserProfileInformation;
use App\Http\Responses\TwoFactorLoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->singleton(
            \Laravel\Fortify\Contracts\TwoFactorLoginResponse::class,
            TwoFactorLoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY ACTIONS: Infrastructure Adapters -> Application Layer
        // ═══════════════════════════════════════════════════════════════════════
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::resetUserPasswordsUsing(ResetPasswordAction::class);

        // Rate Limiters
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->session()->get('login.id', 'missing').'|'.$request->ip());
        });

        // Views (if needed, but actions are the priority)
        // Checks if views exist or basic registration
        if (config('fortify.views', true)) {
            Fortify::registerView(fn () => view('auth.register'));
            Fortify::loginView(fn () => view('auth.login'));
            Fortify::verifyEmailView(fn () => view('auth.verify-email'));
            Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
            Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));
            Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
            Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['request' => $request]));
        }
    }
}
