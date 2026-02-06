<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * ValidatableCommand - Contract for commands requiring validation
 */
interface ValidatableCommandInterface
{
    /**
     * Get the validation rules that apply to the command.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;
}
