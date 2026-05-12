<?php

declare(strict_types=1);

/**
 * 🏗️ ArchTest - Guardian of the Hexagonal Architecture
 *
 * This file acts as the "Supreme Court" of codebase governance.
 * It enforces strict rules for Domain Partitioning, Immutability, and Layer Isolation.
 *
 * @see https://pestphp.com/docs/arch-testing
 */

/*
|--------------------------------------------------------------------------
| 1. Dynamic Component Discovery (Selectors)
|--------------------------------------------------------------------------
| Defines strict sets of classes based on their namespace patterns.
| This allows automatic discovery of new modules (e.g. Billing, Sales).
*/

/*
|--------------------------------------------------------------------------
| 2. Naming Conventions (Cognitive Load Reduction)
|--------------------------------------------------------------------------
| Enforces strict suffixes to ensure "Screaming Architecture".
*/

arch('Handlers must be named with suffix Handler')
    ->expect('App\Application\*\Handlers')
    ->classes()
    ->toHaveSuffix('Handler');

arch('Commands must be named with suffix Command')
    ->expect('App\Application\*\Commands')
    ->classes()
    ->toHaveSuffix('Command');

arch('Results must be named with suffix Result')
    ->expect('App\Application\*\Results')
    ->classes()
    ->toHaveSuffix('Result');

arch('Interfaces must be named with suffix Interface')
    ->expect('App\Application')
    ->interfaces()
    ->toHaveSuffix('Interface');

/*
|--------------------------------------------------------------------------
| 3. Class Design & Immutability (Safety First)
|--------------------------------------------------------------------------
| Enforces the use of "final" and "readonly" to prevent inheritance abuse
| and state mutation.
*/

arch('Handlers must be final (Composition over Inheritance)')
    ->expect('App\Application\*\Handlers')
    ->classes()
    ->toBeFinal();

arch('Commands must be final readonly (Immutable Data Carriers)')
    ->expect('App\Application\*\Commands')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('Results must be final readonly (Immutable Data Carriers)')
    ->expect('App\Application\*\Results')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

/*
|--------------------------------------------------------------------------
| 4. The Hexagonal Wall (Layer Isolation)
|--------------------------------------------------------------------------
| The Application layer coordinates the Domain but MUST NOT know about
| implementation details (HTTP, Eloquent, etc).
*/

arch('Application Layer must strictly isolate from Infrastructure and HTTP')
    ->expect('App\Application')
    ->not->toUse([
        // ZERO ELOQUENT TOLERANCE
        'Illuminate\Database\Eloquent\Model',
        'App\Models',

        // ZERO HTTP TOLERANCE
        'Illuminate\Http\Request',
        'Illuminate\Http\Response',
        'Illuminate\Http\JsonResponse',
        'App\Http',

        // ZERO DEBUGGING IN PRODUCTION
        'dd', 'dump', 'ray', 'var_dump',

        // ZERO UI COUPLING
        'Illuminate\Support\Facades\View',
        'Illuminate\Support\Facades\Session',
    ]);

arch('Application Layer must NOT contain Global Folders (Modular Strictness)')
    ->expect('App\Application')
    ->not->toUse([
        'App\Application\Validators', // MUST use Module/Validators
        'App\Application\Jobs',       // MUST use Infrastructure/Jobs
        'App\Application\Services',   // MUST use Module/Actions or Domain Services
    ]);

test('Root of Application must remain pure and modular (Orphan Ban)', function (): void {
    foreach ([
        'Listeners',
        'Transformers',
        'Middleware',
        'Mail',
        'Services',
        'Jobs',
        'Contracts',
    ] as $forbiddenDirectory) {
        expect(is_dir(dirname(__DIR__, 2).'/app/Application/'.$forbiddenDirectory))
            ->toBeFalse('Application root must not contain '.$forbiddenDirectory);
    }
});

arch('App\Application root should only contain Modules (and Shared/Contracts)')
    ->expect('App\Application')
    ->toOnlyUse([
        'App\Application\*\Commands',
        'App\Application\*\Handlers',
        'App\Application\*\Results',
        'App\Application\*\Validators', // Module-scoped is fine
        'App\Application\*\Actions',
        'App\Application\Shared',
        'App\Application\Shared\Contracts',
        // Allow itself (submodules)
        'App\Application',
        'App\Domain',
    ]);

/*
|--------------------------------------------------------------------------
| 5. Domain Purity (Sovereignty)
|--------------------------------------------------------------------------
| The Domain is the King. It knows nothing about the outside world.
*/

arch('Domain Layer must be independent and pure')
    ->expect('App\Domain')
    ->not->toUse([
        'App\Application',
        'App\Infrastructure',
        'App\Http',
        'App\Models', // Domain Entities != Eloquent Models
        'Illuminate\Http',
        'Illuminate\Support\Facades', // Facades leak global state
    ])
    ->ignoring([
        App\Domain\TenantGovernance\Services\TenantOnboardingProgressService::class,
        App\Domain\AIEnrichment\Jobs\EnhanceTenantProfileJob::class,
        App\Domain\TenantGovernance\Events\TenantProfileEnhanced::class,
    ]);

/*
|--------------------------------------------------------------------------
| 6. Global Health Checks
|--------------------------------------------------------------------------
*/

arch('No Facades in Domain or Commands')
    ->expect(['App\Domain', 'App\Application\*\Commands'])
    ->not->toUse('Illuminate\Support\Facades');
