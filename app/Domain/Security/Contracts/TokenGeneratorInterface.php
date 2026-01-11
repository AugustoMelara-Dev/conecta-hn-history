<?php

declare(strict_types=1);

namespace App\Domain\Security\Contracts;

interface TokenGeneratorInterface
{
    /**
     * Generate a cryptographic secure random string.
     * @param int $length Length of the random string in bytes
     */
    public function generate(int $length = 32): string;
}
