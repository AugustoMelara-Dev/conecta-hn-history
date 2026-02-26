<?php

declare(strict_types=1);

namespace App\Rules;

use App\Application\Identity\Contracts\UserUniquenessCheckerInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * UniqueUser - Domain-Safe Validation Rule
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  BRIDGE: Connects Laravel Validation to the Application Port.          ║
 * ║  REPLACES: Rule::unique(User::class) / Rule::unique('users')          ║
 * ║  TENANT AWARE: Scopes uniqueness check per tenant boundary.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UniqueUser implements ValidationRule
{
    public function __construct(
        private UserUniquenessCheckerInterface $checker,
        private ?string $tenantId = null,
        private ?string $exceptUserId = null,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (! $this->checker->isEmailUnique($value, $this->exceptUserId, $this->tenantId)) {
            $fail('The :attribute has already been taken.');
        }
    }
}
