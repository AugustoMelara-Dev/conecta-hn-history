<?php

declare(strict_types=1);

namespace App\Application\Common\Contracts;

interface HashableCommandInterface
{
    /**
     * Unique Idempotency Key (UUID v7 or similar)
     */
    public function getIdempotencyKey(): string;

    /**
     * Tenant Context
     */
    public function getTenantId(): string;

    /**
     * Deterministic Hash of the Command Payload (for Integrity Check)
     */
    public function getCommandHash(): string;
}
