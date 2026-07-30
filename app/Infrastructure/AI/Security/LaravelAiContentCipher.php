<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Security;

use App\Application\Ai\Security\AiContentCipher;
use App\Application\Ai\Security\EncryptedAiContent;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class LaravelAiContentCipher implements AiContentCipher
{
    public function encrypt(string $tenantId, string $recordId, string $contentClass, string $plaintext): EncryptedAiContent
    {
        $keyVersion = strtolower(trim((string) config(
            'ai.execution_retention.encryption_key_version',
            'v1',
        )));
        if (preg_match('/^[a-z][a-z0-9._-]{0,31}$/', $keyVersion) !== 1) {
            throw new RuntimeException('AI encryption key version is invalid.');
        }
        $associated = $this->associated($tenantId, $recordId, $contentClass, $keyVersion);
        $payload = json_encode([
            'aad_hash' => hash('sha256', $associated),
            'content' => $plaintext,
        ], JSON_THROW_ON_ERROR);

        return new EncryptedAiContent(
            ciphertext: Crypt::encryptString($payload),
            contentHash: hash('sha256', $plaintext),
            keyVersion: $keyVersion,
        );
    }

    public function decrypt(string $tenantId, string $recordId, string $contentClass, EncryptedAiContent $content): string
    {
        try {
            /** @var array{aad_hash?: mixed, content?: mixed} $payload */
            $payload = json_decode(Crypt::decryptString($content->ciphertext), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to decrypt AI content.', 0, $exception);
        }

        $associated = $this->associated($tenantId, $recordId, $contentClass, $content->keyVersion);
        if (! is_string($payload['aad_hash'] ?? null)
            || ! hash_equals(hash('sha256', $associated), $payload['aad_hash'])
            || ! is_string($payload['content'] ?? null)
            || ! hash_equals($content->contentHash, hash('sha256', $payload['content']))) {
            throw new RuntimeException('AI content associated data validation failed.');
        }

        return $payload['content'];
    }

    private function associated(string $tenantId, string $recordId, string $contentClass, string $keyVersion): string
    {
        return implode('|', [$tenantId, $recordId, $contentClass, $keyVersion]);
    }
}
