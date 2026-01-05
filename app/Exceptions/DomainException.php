<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Contracts\Exceptions\HttpDomainException;

/**
 * DomainException - Base Class for All Domain Exceptions
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADR-003: Exception Governance                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  All domain exceptions MUST extend this class.                          ║
 * ║  This enables:                                                           ║
 * ║    - Centralized catch in Application Layer                             ║
 * ║    - Consistent context() method for auditing                           ║
 * ║    - Security validation of context (no sensitive data)                 ║
 * ║    - Standard HTTP status code mapping                                  ║
 * ║    - Canonical JSON serialization via toArray()                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
abstract class DomainException extends \Exception implements HttpDomainException
{
    /**
     * Keys that MUST NEVER appear in exception context.
     * Prevents security risks if logs are compromised.
     */
    protected const FORBIDDEN_CONTEXT_KEYS = [
        'password',
        'api_key',
        'apikey',
        'token',
        'secret',
        'private_key',
        'credit_card',
        'ssn',
        'pin',
        'access_token',
        'refresh_token',
        'authorization',
        'bearer',
        'x-api-key',
    ];

    /**
     * Patterns to detect sensitive data in string values.
     */
    protected const SENSITIVE_VALUE_PATTERNS = [
        '/password\s*[:=]/i',
        '/api[_-]?key\s*[:=]/i',
        '/bearer\s+[a-zA-Z0-9]/i',
        '/\b\d{13,16}\b/',  // Credit card numbers
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // Abstract Methods (Must be implemented by child classes)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Returns context data for logging/auditing.
     * MUST be safe to log (no sensitive data).
     *
     * @return array<string, mixed>
     */
    abstract public function context(): array;

    /**
     * Returns a machine-readable error code in SCREAMING_SNAKE_CASE.
     * Examples: 'INVALID_COUPON', 'TENANT_MISMATCH', 'DATA_INTEGRITY_VIOLATION'
     *
     * @return string
     */
    abstract public function getErrorCode(): string;

    // ═══════════════════════════════════════════════════════════════════════
    // HttpDomainException Interface Implementation
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Returns the HTTP status code for this exception.
     * Default: 400 (Bad Request). Override in child classes.
     */
    public function getHttpStatusCode(): int
    {
        return 400; // Bad Request - default
    }

    /**
     * Serializes the exception for standardized JSON API responses.
     * CANONICAL STRUCTURE - Do not override unless adding extra fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'    => false,
            'error_code' => $this->getErrorCode(),
            'message'    => $this->getMessage(),
            'meta'       => $this->context(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Utility Methods
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get audit-safe message for logging.
     * Includes context for traceability.
     */
    final public function getAuditMessage(): string
    {
        return sprintf(
            "[%s] %s | Context: %s",
            static::class,
            $this->getMessage(),
            json_encode($this->context(), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Validate that context doesn't contain forbidden keys or sensitive values.
     * Throws LogicException if security risk detected.
     * 
     * STABILITY PATCH (2026-01-04): Added recursion depth check to prevent Stack Overflow.
     *
     * @throws \LogicException If forbidden key, sensitive value, or recursion depth exceeded.
     */
    protected function validateContextSafety(array $context, int $depth = 0): void
    {
        // DOS PROTECTION: Max recursion depth
        if ($depth > 3) {
            throw new \LogicException(
                "Exception context exceeds maximum recursion depth (3). " .
                "Simplify context data to prevent Stack Overflow DoS."
            );
        }

        $keys = array_map('strtolower', array_keys($context));

        // Check forbidden keys
        foreach (self::FORBIDDEN_CONTEXT_KEYS as $forbidden) {
            if (in_array($forbidden, $keys, true)) {
                throw new \LogicException(
                    "Exception context cannot contain '{$forbidden}'. Security risk. " .
                    "Remove sensitive data before creating exception."
                );
            }
        }

        // Check values
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                // Recursive check for nested arrays with incremented depth
                $this->validateContextSafety($value, $depth + 1);
            } elseif (is_string($value)) {
                // Check for sensitive patterns in string values
                foreach (self::SENSITIVE_VALUE_PATTERNS as $pattern) {
                    if (preg_match($pattern, $value)) {
                        throw new \LogicException(
                            "Exception context value for '{$key}' contains sensitive data pattern. " .
                            "Remove sensitive data before creating exception."
                        );
                    }
                }
            } elseif (is_object($value)) {
                // SECURITY: Prevent object serialization (Eloquent models, etc.)
                // Context must contain only primitive values (scalars or arrays).
                $type = get_class($value);
                throw new \LogicException(
                    "Exception context value for '{$key}' is an object ({$type}). " .
                    "Only primitives (int, string, bool, array) are allowed to prevent data leakage."
                );
            }
        }
    }
}
