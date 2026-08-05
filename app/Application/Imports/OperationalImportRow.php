<?php

declare(strict_types=1);

namespace App\Application\Imports;

final readonly class OperationalImportRow
{
    /**
     * @param  array<string, string|null>  $source
     * @param  array<string, mixed>  $normalized
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $rowNumber,
        public array $source,
        public array $normalized,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
