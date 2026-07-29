<?php

declare(strict_types=1);

namespace App\Application\Ai\Security;

interface AiContentCipher
{
    public function encrypt(string $tenantId, string $recordId, string $contentClass, string $plaintext): EncryptedAiContent;

    public function decrypt(string $tenantId, string $recordId, string $contentClass, EncryptedAiContent $content): string;
}
