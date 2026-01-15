<?php

declare(strict_types=1);

namespace App\Application\Identity\Validators;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Validators\Rules\UniqueTenantSlugRule;
use App\Application\Identity\Validators\Rules\UniqueUserEmailRule;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Illuminate\Validation\Rules\Password;

final class RegisterUserValidator
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
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
