<?php

declare(strict_types=1);

namespace App\Application\Identity\Validators;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Validators\Rules\UniqueTenantSlugRule;
use App\Application\Identity\Validators\Rules\UniqueUserEmailRule;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * RegisterUserValidator - Executable Validation Service
 *
 * CRITICAL FIX: Removed Facade dependency.
 * Now uses injected ValidatorFactory (Illuminate\Contracts\Validation\Factory)
 * instead of the static Validator Facade.
 *
 * This enables:
 * - Proper dependency injection
 * - Unit testing with mocks
 * - DIP compliance
 */
final readonly class RegisterUserValidator
{
    public function __construct(
        private ValidatorFactory $validatorFactory,
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Validate the RegisterUserCommand and throw on failure.
     *
     * @throws ValidationException If validation fails
     */
    public function validate(RegisterUserCommand $command): void
    {
        $data = [
            'name' => $command->name,
            'email' => $command->email,
            'password' => $command->password,
            'tenantName' => $command->tenantName,
            'slug' => $command->slug,
        ];

        $validator = $this->validatorFactory->make($data, $this->rules($command));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Get validation rules for the RegisterUserCommand.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(RegisterUserCommand $command): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                new UniqueUserEmailRule($this->userRepository),
            ],
            'password' => ['required', 'string', Password::defaults()],
            'tenantName' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                new UniqueTenantSlugRule($this->tenantRepository)
            ],
        ];
    }
}
