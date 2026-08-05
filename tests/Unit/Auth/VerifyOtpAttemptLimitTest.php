<?php

declare(strict_types=1);

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandler;
use App\Application\Shared\Contracts\OutboxRepositoryInterface;
use App\Domain\Auth\Entities\OtpEntity;
use App\Domain\Auth\Exceptions\MaxOtpAttemptsExceededException;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use Ramsey\Uuid\Uuid;

it('invalidates a maxed OTP before a correct code can be accepted', function (): void {
    $tenantId = Uuid::uuid7()->toString();
    $otp = new OtpEntity(
        id: Uuid::uuid7()->toString(),
        userId: Uuid::uuid7()->toString(),
        identifier: 'owner@example.test',
        tenantId: $tenantId,
        hashedCode: 'hashed',
        type: 'email',
        expiresAt: new DateTimeImmutable('+5 minutes'),
        attempts: 5,
    );
    $repository = Mockery::mock(OtpRepositoryInterface::class);
    $repository->shouldReceive('findValidByIdentifier')->once()->andReturn($otp);
    $repository->shouldReceive('delete')->once()->with($otp);
    $hasher = Mockery::mock(OtpHasherInterface::class);
    $hasher->shouldNotReceive('verify');
    $clock = Mockery::mock(ClockInterface::class);
    $clock->shouldReceive('now')->once()->andReturn(new DateTimeImmutable);

    $handler = new VerifyOtpHandler(
        otpRepository: $repository,
        userRepository: Mockery::mock(UserRepositoryInterface::class),
        outboxRepository: Mockery::mock(OutboxRepositoryInterface::class),
        hasher: $hasher,
        clock: $clock,
        maxOtpAttempts: 5,
    );

    $handler->handle(new VerifyOtpCommand(
        tenantId: $tenantId,
        identifier: 'owner@example.test',
        code: '123456',
        ipAddress: '203.0.113.8',
        idempotencyKey: Uuid::uuid7()->toString(),
    ));
})->throws(MaxOtpAttemptsExceededException::class);
