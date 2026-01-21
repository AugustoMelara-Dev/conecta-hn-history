<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use Illuminate\Http\Request;

interface IdempotencyLedgerInterface
{
    /**
     * Check if the request has already been processed based on the state of truth.
     * Returns true if the operation is confirmed complete.
     */
    public function hasProcessed(Request $request, string $idempotencyKey): bool;
}
