<?php

declare(strict_types=1);

namespace App\Application\Identity\Results;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * RegisterUserResult - Immutable Output Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRICT RETURN TYPE: Guarantees API stability and type safety.          ║
 * ║  READONLY: Prevents accidental mutation of response data.               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
#[TypeScript]
final readonly class RegisterUserResult
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $email,
        public bool $requiresVerification,
    ) {}
}
