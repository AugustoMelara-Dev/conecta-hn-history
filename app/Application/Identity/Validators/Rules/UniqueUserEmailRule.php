<?php

declare(strict_types=1);

namespace App\Application\Identity\Validators\Rules;

use App\Domain\Identity\Repositories\UserRepositoryInterface;
use Closure;
use App\Application\Shared\Contracts\ValidationRuleInterface;

/**
 * UniqueUserEmailRule - Optimized for Performance
 */
final readonly class UniqueUserEmailRule implements ValidationRuleInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if ($this->userRepository->existsByEmail($value)) {
            $fail('Este correo electrónico ya está registrado.');
        }
    }
}
