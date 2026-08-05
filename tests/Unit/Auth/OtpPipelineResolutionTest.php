<?php

declare(strict_types=1);

use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;

uses(Tests\TestCase::class);

it('resolves both current otp pipelines from the container', function (): void {
    expect(app(GenerateOtpHandlerInterface::class))->toBeInstanceOf(GenerateOtpHandlerInterface::class)
        ->and(app(VerifyOtpHandlerInterface::class))->toBeInstanceOf(VerifyOtpHandlerInterface::class);
});

it('serializes OTP consumption and does not cache a replayable verification result', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 3).'/app/Providers/AppServiceProvider.php');
    $repository = file_get_contents(dirname(__DIR__, 3).'/app/Infrastructure/Auth/Repositories/EloquentOtpRepository.php');

    expect($provider)
        ->not->toContain('AtomicVerifyOtpHandler::class')
        ->and($repository)
        ->toContain('->lockForUpdate()');

    $middleware = file_get_contents(dirname(__DIR__, 3).'/app/Http/Middleware/InitializeAuthenticatedTenantContext.php');
    expect($middleware)->toContain("'tenant:'.\$tenant->getKey()");
});
