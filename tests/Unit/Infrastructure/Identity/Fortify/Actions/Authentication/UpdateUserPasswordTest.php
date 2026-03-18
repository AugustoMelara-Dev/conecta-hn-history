<?php

declare(strict_types=1);

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\IdGeneratorInterface;
use App\Application\Shared\Contracts\TenantAwareAuthenticatable;
use App\Infrastructure\Identity\Fortify\Actions\Authentication\UpdateUserPassword;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Contracts\Validation\Validator;

// uses(\Tests\TestCase::class);

test('update user password action uses id generator for idempotency key', function () {
    // Arrange
    $commandBus = Mockery::mock(CommandBusInterface::class);
    $validatorFactory = Mockery::mock(ValidatorFactory::class);
    $idGenerator = Mockery::mock(IdGeneratorInterface::class);
    $user = Mockery::mock(TenantAwareAuthenticatable::class);
    $validator = Mockery::mock(Validator::class);
    $rateLimiter = Mockery::mock(\App\Application\Shared\Contracts\TenantRateLimiterInterface::class);

    $uuid = '550e8400-e29b-41d4-a716-446655440000';

    // User Mocks
    $user->shouldReceive('getAuthIdentifier')->andReturn('user-123');
    $user->shouldReceive('getTenantId')->andReturn('tenant-456');

    // Validation Mocks
    $validatorFactory->shouldReceive('make')
        ->once()
        ->andReturn($validator);

    $validator->shouldReceive('validate')
        ->once();

    // IdGenerator Mock
    $idGenerator->shouldReceive('generate')
        ->once()
        ->andReturn($uuid);

    // CommandBus Mock Assertion
    $commandBus->shouldReceive('dispatch')
        ->once()
        ->withArgs(function (UpdateUserPasswordCommand $command) use ($uuid) {
            return $command->idempotencyKey === $uuid
                && $command->userId === 'user-123'
                && $command->requestingTenantId === 'tenant-456';
        });

    // Rate Limiter Mock
    $rateLimiter->shouldReceive('ensureNotExceeded')
        ->once();

    $action = new UpdateUserPassword($commandBus, $validatorFactory, $idGenerator, $rateLimiter);

    // Act
    $action->update($user, [
        'current_password' => 'secret',
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ]);
});
