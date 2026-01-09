<?php

declare(strict_types=1);

/**
 * IdentityServiceProvider - Domain Wiring
 *
 * PURPOSE: Register bindings, events, and policies specific to the Identity domain.
 * MIGRATED: From App\Domain\Identity to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind domain contracts to implementations
        // Example: $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event listeners for Identity domain
        // Example: Event::listen(UserCreated::class, SendWelcomeEmail::class);

        // Register policies
        // Gate::policy(User::class, UserPolicy::class);
    }
}
