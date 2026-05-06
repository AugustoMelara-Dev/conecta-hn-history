<?php

declare(strict_types=1);

/**
 * ╔════════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL ARCHITECTURE GOVERNANCE - "The Automated Wall of Containment"  ║
 * ╠════════════════════════════════════════════════════════════════════════════╣
 * ║  These tests are executed on EVERY COMMIT in CI/CD.                        ║
 * ║  If ANY rule fails, the pipeline BLOCKS the merge.                         ║
 * ║  We LEGISLATE architecture, we do NOT suggest it.                          ║
 * ╚════════════════════════════════════════════════════════════════════════════╝
 *
 * Run with: php artisan test tests/Arch/DomainStructureTest.php
 */

use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

// ═══════════════════════════════════════════════════════════════════════════════
// RULE 1: DOMAIN PURITY SHIELD (NO INFRASTRUCTURE LEAK)
// ═══════════════════════════════════════════════════════════════════════════════

arch('RULE 1.1: Domain Layer must NOT contain Jobs (ShouldQueue)')
    ->expect('App\Domain')
    ->not->toImplement(ShouldQueue::class)
    ->ignoring([
        // EXCEPTION: Legacy Proxy for Backward Compatibility
        // @deprecated Scheduled for removal after 2026-03-05
        'App\Domain\ReportingAndExports\Jobs\GenerateSalesReportJob',
    ]);

arch('RULE 1.2: Domain Layer must NOT contain Mailables')
    ->expect('App\Domain')
    ->not->toExtend(Mailable::class);

arch('RULE 1.3: Domain Layer must NOT contain Notifications')
    ->expect('App\Domain')
    ->not->toExtend(Notification::class);

arch('RULE 1.4: Domain Layer must NOT contain Artisan Commands')
    ->expect('App\Domain')
    ->not->toExtend(Command::class);

arch('RULE 1.5: Application Layer SHOULD contain all Jobs')
    ->expect('App\Infrastructure\Jobs')
    ->toImplement(ShouldQueue::class);

// ═══════════════════════════════════════════════════════════════════════════════
// RULE 2: LAYER SEGREGATION (DEPENDENCY RULE)
// ═══════════════════════════════════════════════════════════════════════════════

arch('RULE 2.1: Application Layer must NOT depend on HTTP Controllers')
    ->expect('App\Application')
    ->not->toUse('App\Http\Controllers');

arch('RULE 2.2: Application Layer must NOT depend on HTTP Requests')
    ->expect('App\Application')
    ->not->toUse('App\Http\Requests');

arch('RULE 2.3: Domain Layer must NOT depend on Application Layer')
    ->expect('App\Domain')
    ->not->toUse('App\Application')
    ->ignoring([
        // Legacy Proxy dispatches to Application Layer (intentional)
        'App\Domain\ReportingAndExports\Jobs\GenerateSalesReportJob',
    ]);

arch('RULE 2.4: Domain must NOT use HTTP Request facade/class')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Http\Request');

// ═══════════════════════════════════════════════════════════════════════════════
// RULE 3: CODE HYGIENE & SECURITY
// ═══════════════════════════════════════════════════════════════════════════════

arch('RULE 3.1: Application code must use strict_types')
    ->expect('App')
    ->toUseStrictTypes();

arch('RULE 3.2: No dd() in production code')
    ->expect('App')
    ->not->toUse('dd');

arch('RULE 3.3: No dump() in production code')
    ->expect('App')
    ->not->toUse('dump');

arch('RULE 3.4: No ray() in production code')
    ->expect('App')
    ->not->toUse('ray');

arch('RULE 3.5: No var_dump() in production code')
    ->expect('App')
    ->not->toUse('var_dump');

// ═══════════════════════════════════════════════════════════════════════════════
// RULE 4: VALUE OBJECTS INTEGRITY (IMMUTABILITY ENFORCEMENT)
// ═══════════════════════════════════════════════════════════════════════════════

arch('RULE 4.1: Value Objects must be FINAL (no inheritance)')
    ->expect('App\Domain\Shared\ValueObjects')
    ->toBeFinal();

arch('RULE 4.2: Value Objects must be READONLY (immutable state)')
    ->expect('App\Domain\Shared\ValueObjects')
    ->toBeReadonly();
