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
        $snakeCase = preg_replace('/(?<!^)[A-Z]/', '_$0', trim($key));
        $normalized = strtolower((string) preg_replace(
            '/[^a-zA-Z0-9]+/',
            '_',
            (string) $snakeCase,
        ));
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
            'credential',
        ], true)
            || str_ends_with($normalized, '_token')
            || str_contains($normalized, 'authorization')
            || str_contains($normalized, 'private_key')
            || str_contains($normalized, 'access_key')
            || str_contains($normalized, 'client_secret')
            || str_contains($normalized, 'api_key')
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'bearer')
            || str_contains($normalized, 'credential');
    }

    private function credentialValue(string $value, int $depth = 0): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }
        if (preg_match(
            '/(?:^|[\s"\'\\[\\]{}(),;?&#])(?:authorization|private[\s_-]*key|access[\s_-]*key|access[\s_-]*token|auth[\s_-]*token|client[\s_-]*secret|api[\s_-]*key|password|secret|bearer|token|credential)\s*[:=]\s*\S+/i',
            $trimmed,
        ) === 1
            || preg_match('/\bbearer\s+\S+/i', $trimmed) === 1
            || preg_match('/(?:^|\s)sk-[A-Za-z0-9_-]{8,}/i', $trimmed) === 1
            || preg_match('/\bAKIA[A-Z0-9]{12,}\b/', $trimmed) === 1
            || str_contains($trimmed, '-----BEGIN PRIVATE KEY-----')
            || preg_match(
                '/\beyJ[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}\.[A-Za-z0-9_-]{6,}\b/',
                $trimmed,
            ) === 1
            || preg_match('/\b[a-fA-F0-9]{40,}\b/', $trimmed) === 1) {
            return true;
        }
        if ($this->credentialUri($trimmed, $depth)) {
            return true;
        }
        if ($depth < 2) {
            $urlDecoded = rawurldecode($trimmed);
            if (! hash_equals($trimmed, $urlDecoded)
                && $this->credentialValue($urlDecoded, $depth + 1)) {
                return true;
            }
            $base64Decoded = $this->base64DecodedText($trimmed);
            if ($base64Decoded !== null
                && $this->credentialValue($base64Decoded, $depth + 1)) {
                return true;
            }
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

    private function credentialUri(string $value, int $depth): bool
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) !== 1
            && ! str_contains($value, '?')
            && ! str_contains($value, '#')) {
            return false;
        }
        $parts = parse_url($value);
        if (! is_array($parts)) {
            return false;
        }
        if (($parts['user'] ?? '') !== '' || ($parts['pass'] ?? '') !== '') {
            return true;
        }
        foreach (['query', 'fragment'] as $component) {
            if (! isset($parts[$component]) || ! is_string($parts[$component])) {
                continue;
            }
            parse_str($parts[$component], $parameters);
            foreach ($parameters as $key => $parameter) {
                if ($this->credentialKey((string) $key)) {
                    return true;
                }
                if (is_string($parameter)
                    && $depth < 2
                    && $this->credentialValue($parameter, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function base64DecodedText(string $value): ?string
    {
        $length = strlen($value);
        if ($length < 8
            || $length > 4096
            || preg_match('/^[A-Za-z0-9+\/_-]+={0,2}$/', $value) !== 1) {
            return null;
        }
        $standard = strtr($value, '-_', '+/');
        $padding = strlen($standard) % 4;
        if ($padding !== 0) {
            $standard .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($standard, true);
        if (! is_string($decoded)
            || $decoded === ''
            || strlen($decoded) > 3072
            || preg_match('//u', $decoded) !== 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $decoded) === 1) {
            return null;
        }
        $unpadded = rtrim($value, '=');
        $canonical = rtrim(base64_encode($decoded), '=');
        $canonicalUrl = strtr($canonical, '+/', '-_');
        if (! hash_equals($unpadded, $canonical)
            && ! hash_equals($unpadded, $canonicalUrl)) {
            return null;
        }

        return $decoded;
    }

    private function reject(string $key): never
    {
        throw ValidationException::withMessages([
            $key => 'Credential material is forbidden in the AI registry.',
        ]);
    }
}
