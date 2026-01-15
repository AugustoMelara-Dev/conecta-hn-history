<?php

declare(strict_types=1);

namespace App\Application\Identity\Results;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * RegisterUserResult - Immutable Output Contract
 *
 * Implements strict JSON serialization to avoid PHP class coupling.
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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'email' => $this->email,
            'requiresVerification' => $this->requiresVerification,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            tenantId: (int) $data['tenantId'],
            email: (string) $data['email'],
            requiresVerification: (bool) $data['requiresVerification'],
        );
    }
}
