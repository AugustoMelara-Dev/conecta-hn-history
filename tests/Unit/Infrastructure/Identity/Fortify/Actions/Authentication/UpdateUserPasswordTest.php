<?php

declare(strict_types=1);

use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\IdGeneratorInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use App\Infrastructure\Identity\Fortify\Actions\Authentication\UpdateUserPassword;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;

test('update user password action uses id generator for idempotency key', function () {
    $commandBus = new RecordingPasswordCommandBus;
    $validatorFactory = new RecordingValidatorFactory;
    $idGenerator = new PasswordTestFixedIdGenerator('550e8400-e29b-41d4-a716-446655440000');
    $rateLimiter = new PasswordRecordingTenantRateLimiter;

    $user = new User;
    $user->forceFill([
        'id' => 'user-123',
        'tenant_id' => 'tenant-456',
        'email' => 'user@example.com',
        'name' => 'Test User',
    ]);

    $action = new UpdateUserPassword($commandBus, $validatorFactory, $idGenerator, $rateLimiter);

    /** @var array{current_password: string, password: string, password_confirmation: string} $input */
    $input = [
        'current_password' => 'secret',
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ];

    $action->update($user, $input);

    expect($rateLimiter->calls)->toBe([
        [
            'actionName' => UpdateUserPassword::class,
            'tenantId' => 'tenant-456',
            'maxAttempts' => 5,
            'decayMinutes' => 1,
        ],
    ]);

    expect($validatorFactory->validator->validatedInput)->toBe($input);
    expect($commandBus->commands)->toHaveCount(1);
    expect($commandBus->commands[0])->toBeInstanceOf(UpdateUserPasswordCommand::class);
    expect($commandBus->commands[0]->idempotencyKey)->toBe('550e8400-e29b-41d4-a716-446655440000');
    expect($commandBus->commands[0]->userId)->toBe('user-123');
    expect($commandBus->commands[0]->requestingTenantId)->toBe('tenant-456');
});

final class RecordingPasswordCommandBus implements CommandBusInterface
{
    /** @var list<object> */
    public array $commands = [];

    public function dispatch(object $command): mixed
    {
        $this->commands[] = $command;

        return null;
    }
}

final class PasswordTestFixedIdGenerator implements IdGeneratorInterface
{
    public function __construct(private string $id) {}

    public function generate(): string
    {
        return $this->id;
    }
}

final class PasswordRecordingTenantRateLimiter implements TenantRateLimiterInterface
{
    /**
     * @var list<array{
     *     actionName: string,
     *     tenantId: string,
     *     maxAttempts: int,
     *     decayMinutes: int
     * }>
     */
    public array $calls = [];

    public function ensureNotExceeded(string $actionName, string $tenantId, int $maxAttempts = 100, int $decayMinutes = 1): void
    {
        $this->calls[] = [
            'actionName' => $actionName,
            'tenantId' => $tenantId,
            'maxAttempts' => $maxAttempts,
            'decayMinutes' => $decayMinutes,
        ];
    }
}

final class RecordingValidatorFactory implements ValidatorFactory
{
    public RecordingValidator $validator;

    public function __construct()
    {
        $this->validator = new RecordingValidator;
    }

    public function make(array $data, array $rules, array $messages = [], array $attributes = []): Validator
    {
        $this->validator->validatedInput = $data;

        return $this->validator;
    }

    public function extend($rule, $extension, $message = null): void {}

    public function extendImplicit($rule, $extension, $message = null): void {}

    public function replacer($rule, $replacer): void {}
}

final class RecordingValidator implements Validator
{
    /** @var array<string, string> */
    public array $validatedInput = [];

    public function validate(): array
    {
        return $this->validatedInput;
    }

    public function validated(): array
    {
        return $this->validatedInput;
    }

    public function fails(): bool
    {
        return false;
    }

    public function failed(): array
    {
        return [];
    }

    public function sometimes($attribute, $rules, callable $callback): static
    {
        return $this;
    }

    public function after($callback): static
    {
        return $this;
    }

    public function errors(): MessageBag
    {
        return new MessageBag;
    }

    public function getMessageBag(): MessageBag
    {
        return new MessageBag;
    }
}
