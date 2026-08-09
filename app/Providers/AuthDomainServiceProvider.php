<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Shared\Contracts\SensitivePayloadCipherInterface;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Shared\Services\PasswordHasherInterface;
use App\Infrastructure\Auth\Listeners\SendOtpNotificationListener;
use App\Infrastructure\Auth\Repositories\EloquentOtpRepository;
use App\Infrastructure\Auth\Services\HmacOtpHasher;
use App\Infrastructure\Notifications\EmailOtpNotifier;
use App\Infrastructure\Security\LaravelSensitivePayloadCipher;
use App\Infrastructure\Shared\Resilience\CircuitBreakerDecorator;
use App\Infrastructure\Shared\Services\LaravelPasswordHasher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Event;
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

        $this->app->bind(
            SensitivePayloadCipherInterface::class,
            LaravelSensitivePayloadCipher::class
        );

        // 3. Bind Password Hashing Service (Shared Infrastructure)
        $this->app->bind(
            PasswordHasherInterface::class,
            LaravelPasswordHasher::class
        );

        // 4. Bind OTP Notifier with Circuit Breaker (Resilience Layer)
        $this->app->bind(OtpNotifierInterface::class, function ($app) {
            // Instantiate concrete provider (e.g., Email or SMS based on config)
            $concreteService = $app->make(EmailOtpNotifier::class);

            // Wrap with Circuit Breaker
            return new CircuitBreakerDecorator(
                innerService: $concreteService,
                cache: $app->make(Repository::class),
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
        Event::listen(
            OtpGenerated::class,
            SendOtpNotificationListener::class
        );
    }
}
