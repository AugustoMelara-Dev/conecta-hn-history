<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Shared\Contracts\DataScrubberInterface;

/**
 * SensitiveDataScrubber - PII Shield
 *
 * Recursively sanitizes data structures to prevent PII leakage in logs.
 * Configurable blacklist of sensitive keys.
 */
final readonly class SensitiveDataScrubber implements DataScrubberInterface
{
    private const DEFAULT_SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'session',
        'otp',
        'code',
        'credit_card',
        'cvv',
        'email',
        'phone',
        'whatsapp_number',
        'secret',
        'api_key',
    ];

    private const REDACTED_MASK = '[REDACTED]';

    /**
     * Scrub sensitive data from the payload.
     *
     * @param  array<mixed>|object  $payload
     * @return array<mixed>
     */
    public function scrub(array|object $payload): array
    {
        // Convert object to array for consistent processing
        $data = is_object($payload) ? (array) $payload : $payload;

        $scrubbed = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $scrubbed[$key] = self::REDACTED_MASK;

                continue;
            }

            if (is_array($value) || is_object($value)) {
                $scrubbed[$key] = $this->scrub($value);

                continue;
            }

            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower($key);

        foreach (self::DEFAULT_SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($normalizedKey, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
