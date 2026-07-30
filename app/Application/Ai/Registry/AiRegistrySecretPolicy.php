<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use Illuminate\Validation\ValidationException;

final class AiRegistrySecretPolicy
{
    /** @param array<string|int, mixed> $payload */
    public function assertSafePayload(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->credentialKey($key)) {
                $this->reject('payload');
            }
            if (is_array($value)) {
                $this->assertSafePayload($value);
            } elseif (is_string($value) && $this->credentialValue($value)) {
                $this->reject('payload');
            }
        }
    }

    public function assertExternalReference(mixed $reference): string
    {
        if (! is_string($reference)) {
            $this->reject('payload.secret_reference');
        }
        $normalized = trim($reference);
        if (preg_match(
            '/^[a-z][a-z0-9_-]{1,63}(?:\.[a-z][a-z0-9_-]{1,63}){1,3}$/',
            $normalized,
        ) !== 1
            || $this->credentialValue($normalized)) {
            $this->reject('payload.secret_reference');
        }

        return $normalized;
    }

    public function assertSafeReason(string $reason): void
    {
        if ($this->credentialValue($reason)) {
            $this->reject('reason');
        }
    }

    public function encryptionKeyVersion(): string
    {
        $version = strtolower(trim((string) config(
            'ai.execution_retention.encryption_key_version',
            'v1',
        )));
        if (preg_match('/^[a-z][a-z0-9._-]{0,31}$/', $version) !== 1) {
            $this->reject('encryption_key_version');
        }

        return $version;
    }

    private function credentialKey(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', trim($key)));
        if (hash_equals('max_output_tokens', $normalized)) {
            return false;
        }

        return in_array($normalized, [
            'token',
            'authorization',
            'private_key',
            'access_key',
            'client_secret',
            'api_key',
            'password',
            'secret',
            'bearer',
        ], true)
            || str_ends_with($normalized, '_token')
            || str_contains($normalized, 'authorization')
            || str_contains($normalized, 'private_key')
            || str_contains($normalized, 'access_key')
            || str_contains($normalized, 'client_secret')
            || str_contains($normalized, 'api_key')
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'bearer');
    }

    private function credentialValue(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }
        if (preg_match('/(?:^|\s)sk-[A-Za-z0-9_-]{8,}/i', $trimmed) === 1
            || preg_match('/\bAKIA[A-Z0-9]{12,}\b/', $trimmed) === 1
            || str_contains($trimmed, '-----BEGIN PRIVATE KEY-----')
            || preg_match(
                '/\beyJ[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}\b/',
                $trimmed,
            ) === 1
            || preg_match('/\b[a-fA-F0-9]{40,}\b/', $trimmed) === 1) {
            return true;
        }
        if (strlen($trimmed) < 32
            || preg_match('/\s/', $trimmed) === 1
            || preg_match('/^[a-f0-9-]{36}$/i', $trimmed) === 1
            || preg_match('/[a-z]/', $trimmed) !== 1
            || preg_match('/[A-Z]/', $trimmed) !== 1
            || preg_match('/\d/', $trimmed) !== 1) {
            return false;
        }

        return count(array_unique(str_split($trimmed))) >= 16;
    }

    private function reject(string $key): never
    {
        throw ValidationException::withMessages([
            $key => 'Credential material is forbidden in the AI registry.',
        ]);
    }
}
