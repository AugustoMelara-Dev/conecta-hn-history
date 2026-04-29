<?php

declare(strict_types=1);

namespace App\Application\Auth\Contracts;

/**
 * Stores the ephemeral delivery payload required to notify an OTP asynchronously.
 *
 * The plain-text code must never be persisted in the primary database, but the
 * outbox worker still needs a short-lived handoff channel to complete delivery.
 */
interface OtpDeliveryPayloadStoreInterface
{
    public function put(
        string $otpId,
        string $plainTextCode,
        string $identifier,
        int $expiryMinutes
    ): void;

    /**
     * @return array{plain_text_code: string, identifier: string, expiry_minutes: int}|null
     */
    public function get(string $otpId): ?array;

    public function forget(string $otpId): void;

    public function markSent(string $otpId, int $expiryMinutes): void;

    public function wasSent(string $otpId): bool;
}
