<?php

declare(strict_types=1);

namespace App\Contracts\Exceptions;

/**
 * HttpDomainException - Contract for all domain exceptions that can be rendered as HTTP responses.
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADR-003: Exception Governance Contract                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This interface ensures:                                                ║
 * ║    - Consistent HTTP status codes across all exceptions                 ║
 * ║    - Machine-readable error codes for frontend handling                 ║
 * ║    - Standardized JSON serialization for API responses                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface HttpDomainException
{
    /**
     * Returns the HTTP status code for this exception.
     * Examples: 400, 403, 404, 409, 413, 422
     *
     * @return int HTTP status code
     */
    public function getHttpStatusCode(): int;

    /**
     * Returns a machine-readable error code in SCREAMING_SNAKE_CASE.
     * Examples: 'INVALID_COUPON', 'TENANT_MISMATCH', 'DATA_INTEGRITY_VIOLATION'
     *
     * This code is used by frontend applications for conditional error handling.
     *
     * @return string Error code
     */
    public function getErrorCode(): string;

    /**
     * Serializes the exception for standardized JSON API responses.
     *
     * Must return canonical structure:
     * [
     *     'success'    => false,
     *     'error_code' => 'ERROR_CODE',
     *     'message'    => 'Human readable message',
     *     'meta'       => [...contextual data...]
     * ]
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
