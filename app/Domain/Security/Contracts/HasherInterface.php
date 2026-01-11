<?php

declare(strict_types=1);

namespace App\Domain\Security\Contracts;

interface HasherInterface
{
    /**
     * Hash the given value.
     */
    public function make(string $value, array $options = []): string;

    /**
     * Check the given plain value against a hash.
     */
    public function check(string $value, string $hashedValue, array $options = []): bool;
}
