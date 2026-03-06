<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Identity\Fortify\Actions\Authentication\ResetPasswordAction;
use App\Infrastructure\Identity\Fortify\Actions\Authentication\UpdateUserPassword;
use App\Infrastructure\Identity\Fortify\Actions\Identity\UpdateUserProfileInformation;
use App\Infrastructure\Identity\Fortify\Actions\Identity\CreateNewUser;
use Illuminate\Cache\RateLimiting\Limit;
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
        if (config('fortify.views', true)) {
            Fortify::registerView(fn () => view('auth.register'));
            Fortify::loginView(fn () => view('auth.login'));
            Fortify::verifyEmailView(fn () => view('auth.verify-email'));
            Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
            Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
            Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
            Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['request' => $request]));
        }
    }
}
