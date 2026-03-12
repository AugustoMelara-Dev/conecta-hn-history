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

test('Interfaces must be named with suffix Interface', function () {
    $bases = [
        realpath(__DIR__ . '/../../app/Application'),
        realpath(__DIR__ . '/../../app/Domain'),
        realpath(__DIR__ . '/../../app/Infrastructure'),
    ];

    $ignored = [
        'App\Application\Identity\Contracts\UserReadProjection',
        'App\Application\Identity\Contracts\UserUniquenessChecker',
        'App\Application\Shared\Contracts\ReadOnlyCommand',
        'App\Application\Shared\Contracts\TenantAware',
    ];

    $checked = 0;

    foreach ($bases as $baseDir) {
        if (! $baseDir || ! is_dir($baseDir)) {
            continue;
        }

        $rootNamespace = match (basename($baseDir)) {
            'Application' => 'App\Application',
            'Domain' => 'App\Domain',
            'Infrastructure' => 'App\Infrastructure',
        };

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $className = $rootNamespace . '\\' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);

            if (! interface_exists($className) || in_array($className, $ignored, true)) {
                continue;
            }

            expect(str_ends_with(class_basename($className), 'Interface'))->toBeTrue(
                "Interface [{$className}] must have suffix Interface."
            );

            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(0);
});

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
    ])
    ->ignoring([
        'App\Application\Sales\Contracts\OrderStateServiceInterface',
    ]);

arch('Application Layer must NOT contain Global Folders (Modular Strictness)')
    ->expect('App\Application')
    ->not->toUse([
        'App\Application\Validators', // MUST use Module/Validators
        'App\Application\Jobs',       // MUST use Infrastructure/Jobs
        'App\Application\Services',   // MUST use Module/Actions or Domain Services
    ]);

test('Root of Application must remain pure and modular (Orphan Ban)', function () {
    $rootFiles = array_values(array_filter(glob(__DIR__ . '/../../app/Application/*.*'), static fn (string $path): bool => basename($path) !== 'README.md'));
    expect($rootFiles)->toHaveCount(0);
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
        // PII Encryption: Domain Events carry encrypted email/phones and require Crypt facade
        'App\Domain\Identity\Events\UserRegistered',
        'App\Domain\Identity\Events\UserRegisteredViaSocial',
        'App\Domain\TenantGovernance\Policies\SlugPolicy',
    ]);

/*
|--------------------------------------------------------------------------
| 6. Global Health Checks
|--------------------------------------------------------------------------
*/

arch('No Facades in Domain or Commands')
    ->expect(['App\Domain', 'App\Application\*\Commands'])
    ->not->toUse('Illuminate\Support\Facades');





















