<?php

declare(strict_types=1);

/**
 * ApplicationTaxonomyTest - Dependency Inversion & ORM Isolation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INVARIANT A: Zero App\Models imports in the Application Layer.       ║
 * ║               Handlers MUST use Repository interfaces, not Eloquent.  ║
 * ║  INVARIANT B: Zero Facade usage in the Application Layer.             ║
 * ║               All dependencies injected via constructor DI.           ║
 * ║  INVARIANT C: Zero SerializesModels trait in Application Jobs.        ║
 * ║               Queue payloads must carry primitives/DTOs only.         ║
 * ║  INVARIANT D: No Eloquent Model extension within Application Layer.   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * These tests permanently seal the Application Layer's boundary against
 * ORM leakage and global state. Violations were eradicated in:
 * - ResetUserPasswordHandler (App\Models\User → UserRepositoryInterface)
 * - TenantMicrositeTransformer (dead App\Models imports removed)
 * - RateLimitedReportPaymentHandler (Facade → injected LoggerInterface)
 * - RateLimitedGenerateUploadUrlHandler (same pattern)
 *
 * @see tests/Architecture/ArchTest.php — Contains overlapping App\Models ban
 * @see tests/Architecture/ApplicationGovernanceTest.php — ConnectionInterface rules
 */

$applicationNamespace = 'App\Application';

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT A: Zero Eloquent Model Imports
// The Application Layer MUST depend on Repository contracts, not on the
// ORM directly. Importing App\Models bypasses the Dependency Inversion
// Principle and creates accidental tight coupling to Eloquent.
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not use eloquent models')
    ->expect($applicationNamespace)
    ->not->toUse('App\Models');

test('application layer does not extend eloquent model')
    ->expect($applicationNamespace)
    ->not->toExtend('Illuminate\Database\Eloquent\Model');

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT B: Zero Facade Usage
// Facades introduce invisible, untestable global state. All dependencies
// MUST be injected via constructor for explicit dependency graphs and
// mockability in unit tests.
// ═══════════════════════════════════════════════════════════════════════════

test('application layer does not use facades')
    ->expect($applicationNamespace)
    ->not->toUse('Illuminate\Support\Facades');

// ═══════════════════════════════════════════════════════════════════════════
// INVARIANT C: Zero SerializesModels in Queue Payloads
// SerializesModels serializes entire Eloquent object graphs into the queue,
// creating massive payloads and tight ORM coupling. Application Jobs MUST
// carry only primitives, Value Objects, or DTOs.
// ═══════════════════════════════════════════════════════════════════════════

test('application layer avoids serializes models trait to prevent ORM coupling in queues')
    ->expect($applicationNamespace)
    ->not->toUse('Illuminate\Queue\SerializesModels');
