<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * SecureSecretCast - Transparent Encryption Layer for Sensitive Data
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Defense-in-Depth for Sensitive Fields         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Manual encryption in controllers is error-prone                ║
 * ║    - Developers forget to encrypt before save                           ║
 * ║    - Developers forget to decrypt after retrieval                       ║
 * ║    - Raw secrets may leak into logs, exceptions, or API responses       ║
 * ║                                                                          ║
 * ║  SOLUTION: Automatic encryption at the model layer                       ║
 * ║    - SET: Plaintext → encryptString() → Ciphertext (stored)             ║
 * ║    - GET: Ciphertext → decryptString() → Plaintext (returned)           ║
 * ║                                                                          ║
 * ║  ENCRYPTION STANDARD: Laravel's Crypt facade uses AES-256-CBC           ║
 * ║    with HMAC integrity verification. Each encryption produces a         ║
 * ║    unique ciphertext due to IV randomization.                           ║
 * ║                                                                          ║
 * ║  KEY ROTATION SAFETY:                                                    ║
 * ║    If APP_KEY changes, old ciphertext cannot be decrypted.              ║
 * ║    This cast handles DecryptException by:                                ║
 * ║      1. Logging a CRITICAL security alert                               ║
 * ║      2. Returning null (fail-safe, not fail-open)                       ║
 * ║    This prevents application crashes while alerting ops team.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE in Model:
 *   protected $casts = [
 *       'api_secret' => SecureSecretCast::class,
 *       'banking_credentials' => SecureSecretCast::class,
 *   ];
 *
 * SECURITY NOTE: Add these columns to $hidden in the model to prevent
 * accidental exposure in JSON responses:
 *   protected $hidden = ['api_secret', 'banking_credentials'];
 */
class SecureSecretCast implements CastsAttributes
{
    /**
     * Decrypt the value when retrieving from database.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value Encrypted ciphertext from database
     * @param array $attributes
     * @return string|null Decrypted plaintext or null on failure
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // Handle null values
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // ═══════════════════════════════════════════════════════════════════════
            // CRITICAL SECURITY ALERT: Decryption Failure
            // ═══════════════════════════════════════════════════════════════════════
            // This indicates one of:
            // 1. APP_KEY rotation without data migration
            // 2. Database corruption
            // 3. Tampering attempt
            //
            // We log at CRITICAL level to trigger ops alerts and return null
            // to prevent application crash. The field will need manual recovery.
            // ═══════════════════════════════════════════════════════════════════════
            Log::critical(
                'SecureSecretCast: Decryption failed - possible key rotation or data corruption',
                [
                    'model' => get_class($model),
                    'model_id' => $model->getKey(),
                    'attribute' => $key,
                    'exception' => $e->getMessage(),
                    // DO NOT log the ciphertext value - it may be sensitive
                ]
            );

            return null;
        }
    }

    /**
     * Encrypt the value when storing to database.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value Plaintext secret or null
     * @param array $attributes
     * @return string|null Encrypted ciphertext for storage
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // Allow null for nullable columns
        if ($value === null || $value === '') {
            return null;
        }

        // Ensure we're working with a string
        if (!is_string($value)) {
            $value = (string) $value;
        }

        // Skip encryption if already encrypted (idempotency guard)
        // Laravel's encrypted strings start with 'eyJ' (base64 encoded JSON)
        if ($this->isAlreadyEncrypted($value)) {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /**
     * Check if a value appears to already be encrypted.
     *
     * Laravel's encryption format is base64-encoded JSON containing
     * iv, value, and mac fields. The base64 typically starts with 'eyJ'.
     *
     * This prevents double-encryption when setting a value that was
     * already encrypted (e.g., during model replication).
     */
    protected function isAlreadyEncrypted(string $value): bool
    {
        // Quick heuristic: Laravel encrypted strings are base64 JSON
        if (strlen($value) < 50 || !str_starts_with($value, 'eyJ')) {
            return false;
        }

        // Attempt to decode and verify structure
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);
        
        return is_array($payload) && 
               isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
