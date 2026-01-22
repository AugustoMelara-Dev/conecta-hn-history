<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * UuidGeneratorInterface - Framework Agnostic Identity Port
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL: Decouples Application Layer from UUID implementation.       ║
 * ║  TESTABLE: Allows deterministic ID generation in unit tests.            ║
 * ║  SWAPPABLE: Ramsey, Symfony UID, or Snowflake implementations.          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface UuidGeneratorInterface
{
    /**
     * Generate a new unique identifier.
     *
     * @return string UUID v4 or equivalent unique identifier
     */
    public function generate(): string;
}
