<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * PayloadTooLargeException - Thrown when input exceeds safe limits.
 *
 * Protects against memory exhaustion DoS attacks via massive JSON payloads.
 */
final class PayloadTooLargeException extends DataIntegrityException
{
    public function __construct(
        string $message,
        array $contextData = [],
        private readonly int $maxSize = 0,
        private readonly int $actualSize = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $contextData, '', [], $previous);
    }

    public function context(): array
    {
        return [
            'max_size' => $this->maxSize,
            'actual_size' => $this->actualSize,
            ...parent::context(),
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 413; // Payload Too Large
    }

    public function getErrorCode(): string
    {
        return 'PAYLOAD_TOO_LARGE';
    }

    public function maxSize(): int
    {
        return $this->maxSize;
    }

    public function actualSize(): int
    {
        return $this->actualSize;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Factory Methods
    // ═══════════════════════════════════════════════════════════════════════

    public static function itemsExceeded(int $count, int $limit): self
    {
        return new self(
            message: "Payload too large: {$count} items exceeds limit of {$limit}.",
            contextData: ['type' => 'items_count'],
            maxSize: $limit,
            actualSize: $count,
        );
    }

    public static function bytesExceeded(int $bytes, int $limit): self
    {
        return new self(
            message: "Payload too large: {$bytes} bytes exceeds limit of {$limit}.",
            contextData: ['type' => 'bytes'],
            maxSize: $limit,
            actualSize: $bytes,
        );
    }
}
