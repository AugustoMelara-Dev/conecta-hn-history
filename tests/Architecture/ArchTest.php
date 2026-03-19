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

test('Handlers must be named with suffix Handler', function () {
    expect('App\Application\*\Handlers')
        ->classes()
        ->toHaveSuffix('Handler');
});

test('Commands must be named with suffix Command', function () {
    expect('App\Application\*\Commands')
        ->classes()
        ->toHaveSuffix('Command');
});

test('Results must be named with suffix Result', function () {
    expect('App\Application\*\Results')
        ->classes()
        ->toHaveSuffix('Result');
});

test('Interfaces must be named with suffix Interface', function () {
    $bases = [
        realpath(__DIR__.'/../../app/Application'),
        realpath(__DIR__.'/../../app/Domain'),
        realpath(__DIR__.'/../../app/Infrastructure'),
    ];

    $ignored = [
        'App\Application\Identity\Contracts\UserReadProjection',
        'App\Application\Identity\Contracts\UserUniquenessChecker',
        'App\Application\Shared\Contracts\ReadOnlyCommand',
        'App\Application\Shared\Contracts\TenantAware',
        'App\Application\Shared\Contracts\TenantAwareAuthenticatable',
        'App\Domain\Analytics\Contracts\AnalyticsReader',
        'App\Domain\Api\Contracts\NotificationRepository',
        'App\Domain\Billing\Contracts\BillingReader',
        'App\Domain\Catalog\Contracts\ProductCatalogReader',
        'App\Domain\Catalog\Contracts\PublicCatalogReader',
        'App\Domain\Common\DomainEvent',
        'App\Domain\Contracts\AdminNotifier',
        'App\Domain\Contracts\SearchRepository',
        'App\Domain\Directory\Contracts\DirectoryReader',
        'App\Domain\Marketing\Contracts\CouponReader',
        'App\Domain\Product\Contracts\ProductReader',
        'App\Domain\Public\Contracts\BuyerRepository',
        'App\Domain\Public\Contracts\LandingPageRepository',
        'App\Domain\Public\Contracts\TenantStorefrontRepository',
        'App\Domain\Sales\Contracts\OrderReader',
        'App\Domain\Sales\Contracts\ReportReader',
        'App\Domain\Search\Contracts\GlobalSearchReader',
        'App\Domain\Shared\Contracts\CityReader',
        'App\Domain\Shared\Contracts\Events\DomainEvent',
        'App\Domain\Shared\Contracts\PaginatedResult',
        'App\Domain\Shared\Contracts\ShouldBeStoredInOutbox',
        'App\Domain\Shared\Contracts\ShouldDispatchAfterCommit',
        'App\Domain\Shared\Contracts\TenantAwareEvent',
        'App\Domain\Shared\Contracts\TenantContext',
        'App\Domain\Shared\Contracts\TenantReadRepository',
        'App\Domain\Tenant\Contracts\BillingRepository',
        'App\Domain\Tenant\Contracts\CouponRepository',
        'App\Domain\Tenant\Contracts\DashboardMetricsRepository',
        'App\Domain\Tenant\Contracts\OnboardingReader',
        'App\Domain\Tenant\Contracts\OrderRepository',
        'App\Domain\Tenant\Contracts\TenantProductRepository',
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
            default => null,
        };

        if ($rootNamespace === null) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($baseDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $className = $rootNamespace.'\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);

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
test('Handlers must be final (Composition over Inheritance)', function () {
    expect('App\Application\*\Handlers')
        ->classes()
        ->toBeFinal();
});

test('Commands must be final readonly (Immutable Data Carriers)', function () {
    expect('App\Application\*\Commands')
        ->classes()
        ->toBeFinal()
        ->toBeReadonly();
});

test('Results must be final readonly (Immutable Data Carriers)', function () {
    expect('App\Application\*\Results')
        ->classes()
        ->toBeFinal()
        ->toBeReadonly();
});

/*
|--------------------------------------------------------------------------
| 4. The Hexagonal Wall (Layer Isolation)
|--------------------------------------------------------------------------
| The Application layer coordinates the Domain but MUST NOT know about
| implementation details (HTTP, Eloquent, etc).
*/

test('Application Layer must strictly isolate from Infrastructure and HTTP', function () {
    expect('App\Application')
        ->not->toUse([
            'Illuminate\Database\Eloquent\Model',
            'App\Models',
            'Illuminate\Http\Request',
            'Illuminate\Http\Response',
            'Illuminate\Http\JsonResponse',
            'App\Http',
            'dd', 'dump', 'ray', 'var_dump',
            'Illuminate\Support\Facades\View',
            'Illuminate\Support\Facades\Session',
        ])
        ->ignoring([
            'App\Application\Sales\Contracts\OrderStateServiceInterface',
        ]);
});

test('Application Layer must NOT contain Global Folders (Modular Strictness)', function () {
    expect('App\Application')
        ->not->toUse([
            'App\Application\Validators',
            'App\Application\Jobs',
            'App\Application\Services',
        ]);
});

test('Root of Application must remain pure and modular (Orphan Ban)', function () {
    $rootFiles = array_values(array_filter(glob(__DIR__.'/../../app/Application/*.*'), static fn (string $path): bool => basename($path) !== 'README.md'));
    expect($rootFiles)->toHaveCount(0);
});

test('App\Application root should only contain Modules (and Shared/Contracts)', function () {
    expect('App\Application')
        ->toOnlyUse([
            'App\Application\*\Commands',
            'App\Application\*\Handlers',
            'App\Application\*\Results',
            'App\Application\*\Validators',
            'App\Application\*\Actions',
            'App\Application\Shared',
            'App\Application\Shared\Contracts',
            'App\Application',
            'App\Domain',
        ]);
});

/*
|--------------------------------------------------------------------------
| 5. Domain Purity (Sovereignty)
|--------------------------------------------------------------------------
| The Domain is the King. It knows nothing about the outside world.
*/

test('Domain Layer must be independent and pure', function () {
    expect('App\Domain')
        ->not->toUse([
            'App\Application',
            'App\Infrastructure',
            'App\Http',
            'App\Models',
            'Filament',
            'Livewire',
            'Illuminate\Http',
            'Illuminate\Support\Facades',
        ])
        ->ignoring([
            'App\Domain\Identity\Events\UserRegistered',
            'App\Domain\Identity\Events\UserRegisteredViaSocial',
            'App\Domain\Identity\Entities\UserEntity',
            'App\Domain\Booking\Services\SlotCalculator',
            'App\Domain\TenantGovernance\Policies\SlugPolicy',
        ]);
});

/*
|--------------------------------------------------------------------------
| 6. Global Health Checks
|--------------------------------------------------------------------------
*/

test('No Facades in Domain or Commands', function () {
    expect(['App\Domain', 'App\Application\*\Commands'])
        ->not->toUse('Illuminate\Support\Facades');
});
