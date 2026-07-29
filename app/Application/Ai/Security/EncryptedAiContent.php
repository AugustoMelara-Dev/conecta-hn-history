<?php

declare(strict_types=1);

namespace App\Application\Ai\Security;

final readonly class EncryptedAiContent
{
    public function __construct(
        public string $ciphertext,
        public string $contentHash,
        public string $keyVersion,
    ) {}
}
