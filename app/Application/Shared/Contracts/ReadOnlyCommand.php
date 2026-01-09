<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * ReadOnlyCommand - Marker Interface for Query Optimization
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PERFORMANCE: Signals TransactionMiddleware to skip DB transaction.     ║
 * ║  USE CASE: Pure data retrieval commands (CQRS Query side).              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface ReadOnlyCommand
{
    // Marker interface - no methods required
}
