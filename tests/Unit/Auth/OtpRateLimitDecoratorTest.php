<?php

declare(strict_types=1);

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Decorators\RateLimitedGenerateOtpHandler;
use App\Application\Auth\Decorators\VerifyOtpRateLimitDecorator;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Ramsey\Uuid\Uuid;

it('blocks otp generation by normalized identifier even when IP addresses rotate', function (): void {
    $inner = Mockery::mock(GenerateOtpHandlerInterface::class);
    $inner->shouldNotReceive('handle');
    $limiter = Mockery::mock(RateLimitServiceInterface::class);
    $limiter->shouldReceive('hitAndCheck')->once()
        ->withArgs(fn (string $key, int $max, int $decay): bool => str_contains($key, ':identifier:') && $max === 3 && $decay === 3600)
        ->andReturnTrue();
    $limiter->shouldReceive('hitAndCheck')->once()
        ->withArgs(fn (string $key, int $max, int $decay): bool => str_starts_with($key, 'otp_generation:ip:') && $max === 9 && $decay === 3600)
        ->andReturnFalse();
    $limiter->shouldReceive('availableIn')->once()->andReturn(120);

    $decorator = new RateLimitedGenerateOtpHandler($inner, $limiter, 3, 3600);

    $decorator->handle(new GenerateOtpCommand(
        tenantId: Uuid::uuid7()->toString(),
        identifier: 'Owner@Example.Test ',
        type: 'email',
        ipAddress: '203.0.113.8',
        userAgent: 'test',
    ));
})->throws(TooManyOtpRequestsException::class);

it('uses atomic identifier and IP buckets for otp verification', function (): void {
    $inner = Mockery::mock(VerifyOtpHandlerInterface::class);
    $inner->shouldNotReceive('handle');
    $limiter = Mockery::mock(RateLimitServiceInterface::class);
    $limiter->shouldReceive('hitAndCheck')->twice()->andReturn(false, true);

    $decorator = new VerifyOtpRateLimitDecorator($inner, $limiter, 5, 60);

    $decorator->handle(new VerifyOtpCommand(
        tenantId: Uuid::uuid7()->toString(),
        identifier: 'owner@example.test',
        code: '123456',
        ipAddress: '203.0.113.8',
        idempotencyKey: Uuid::uuid7()->toString(),
    ));
})->throws(ThrottleRequestsException::class);
