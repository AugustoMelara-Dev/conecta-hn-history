<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

// // use Illuminate\Http\JsonResponse; // REMOVED: Domain layer cannot depend on Http layer // REMOVED: Domain layer cannot depend on Http layer

interface IdempotencyLedgerInterface
{
    /**
     * Check if the request has already been processed based on the state of truth.
     * Returns true if the operation is confirmed complete.
     */
    public function hasProcessed(string $tenantId, string $idempotencyKey): bool;
}
