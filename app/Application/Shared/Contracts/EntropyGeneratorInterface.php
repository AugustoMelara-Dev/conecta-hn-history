<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * EntropyGeneratorInterface - Deterministic Randomness Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TESTABILITY: Allows injecting predictable sequences in tests.          ║
 * ║  SECURITY: Implementations must use CSPRNG (e.g. random_int).           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface EntropyGeneratorInterface
{
    public function generateNumeric(int $digits): string;

    public function generateString(int $length): string;
}
