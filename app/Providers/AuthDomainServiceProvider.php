<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Infrastructure\Auth\Repositories\EloquentOtpRepository;
use App\Infrastructure\Auth\Services\HmacOtpHasher;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AuthDomainServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        // 1. Bind Cryptographic Service (with Config Injection)
        $this->app->bind(OtpHasherInterface::class, function ($app) {
            $secret = $app['config']['app.key'];

            if (empty($secret)) {
                throw new RuntimeException('FATAL: Application key is missing. Auth subsystem cannot start.');
            }

            return new HmacOtpHasher($secret);
        });

        // 2. Bind Persistent Repository
        $this->app->bind(OtpRepositoryInterface::class, EloquentOtpRepository::class);

        // 3. Bind Password Hashing Service (Shared Infrastructure)
        $this->app->bind(
            \App\Domain\Shared\PasswordHasherInterface::class,
            \App\Infrastructure\Shared\Services\LaravelPasswordHasher::class
        );

        // 4. Bind OTP Notifier with Circuit Breaker (Resilience Layer)
        $this->app->bind(\App\Domain\Auth\Contracts\OtpNotifierInterface::class, function ($app) {
            // Instantiate concrete provider (e.g., Email or SMS based on config)
            $concreteService = $app->make(\App\Infrastructure\Notifications\EmailOtpNotifier::class);

            // Wrap with Circuit Breaker
            return new \App\Infrastructure\Shared\Resilience\CircuitBreakerDecorator(
                innerService: $concreteService,
                cache: $app->make(\Illuminate\Contracts\Cache\Repository::class),
                serviceName: 'otp_notifier_email'
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Explicitly register Domain Event Listeners
        // This keeps the Auth module self-contained (Events + Listeners)
        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Auth\Events\OtpGenerated::class,
            \App\Infrastructure\Auth\Listeners\SendOtpNotificationListener::class
        );
    }
}
