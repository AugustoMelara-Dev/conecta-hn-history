<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Closure;

interface ValidationRuleInterface
{
    /**
     * Determine if the validation rule passes.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void;
}
