<?php

declare(strict_types=1);

/**
 * ApplicationGovernanceTest - "Líneas Rojas" Enforcement
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INVARIANT A: Transaction Delegation — Mutations MUST use             ║
 * ║               TransactionManagerInterface, never raw DB connections.  ║
 * ║  INVARIANT B: Anti-Corruption — No direct database coupling via       ║
 * ║               ConnectionInterface, DatabaseManager, or Connection.    ║
 * ║  INVARIANT C: Exception Purity — Application Layer should use         ║
 * ║               domain-level exceptions, not ORM-specific ones.         ║
 * ║  INVARIANT D: TransactionManagerInterface contract MUST exist.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * NOTE: App\Models and Facades prohibition is already enforced by
 * ArchTest.php (lines 82-101). This test covers ADDITIONAL governance
 * invariants for database coupling, transaction delegation, and
 * exception purity.
 *
 * @see tests/Architecture/ArchTest.php — Enforces App\Models + Facades ban
 * @see tests/Architecture/ApplicationPatternsTest.php — Enforces Outbox + Idempotency + Rate Limiting
 */

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT A: Transaction Delegation
// The TransactionManagerInterface MUST be the canonical abstraction for
// all transactional operations. Its existence guarantees that handlers
// can be tested without a database and the ORM can be swapped.
// ═══════════════════════════════════════════════════════════════════════════

test('transaction manager interface exists as canonical transaction port', function () {
    expect('App\Application\Shared\Contracts\TransactionManagerInterface')
        ->toBeInterface();
});

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT B: Anti-Corruption — No Direct Database Coupling
// Handlers MUST NOT inject ConnectionInterface, DatabaseManager, or
// Connection. These create framework coupling that prevents ORM swapping
// and breaks unit test isolation.
//
// ERADICATED: All 3 former violators have been refactored:
// - GetUserProfileHandler → UserReadProjection
// - ReconcilePendingSubscriptionsJob → SubscriptionIntentRepositoryInterface
// - RequestUpgradeHandler → SubscriptionIntentRepositoryInterface
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not use direct database connections', function () {
    expect('App\Application')
        ->not()->toUse([
            'Illuminate\Database\DatabaseManager',
            'Illuminate\Database\Connection',
            'Illuminate\Database\ConnectionInterface',
        ]);
});

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT C: Exception Purity — No ORM-Specific Exceptions
// The Application Layer should throw domain exceptions (DomainException,
// InvalidArgumentException, custom exceptions) rather than catching or
// importing ORM-specific exceptions like QueryException.
//
// DOCUMENTED EXCEPTION:
// - ReportPaymentHandler: Catches QueryException for idempotent duplicate
//   detection at the DB constraint level. Acceptable until domain-level
//   DuplicatePaymentException is fully migrated.
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not import ORM query exceptions', function () {
    expect('App\Application')
        ->not()->toUse('Illuminate\Database\QueryException')
        ->ignoring([
            // Idempotent duplicate detection via DB constraint
            'App\Application\Billing\Handlers\ReportPaymentHandler',
            // Stripe webhook replay recovery still depends on provider idempotency constraints.
            'App\Application\Billing\Listeners\ProcessPaymentWebhookReceived',
        ]);
});
