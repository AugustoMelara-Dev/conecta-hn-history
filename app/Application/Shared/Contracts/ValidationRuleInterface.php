<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use Closure;

interface ValidationRuleInterface
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string  $attribute
     * @param mixed   $value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void;
}
