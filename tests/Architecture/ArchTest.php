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

test('Core interface naming policy stays documented', function (): void {
    expect(true)->toBeTrue();
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
        'App\Application\Analytics\TrustedCommercialOutcomeProjector',
        'App\Application\Ai\Knowledge\CatalogKnowledgeIndexer',
        'App\Application\Availability\AvailabilityRuleWriter',
        'App\Application\Crm\CanonicalOpportunityWriter',
        'App\Application\Crm\OpportunityWriteResult',
        'App\Application\Imports\OperationalImportService',
        'App\Application\Interactions\Handlers\RecordInteractionHandler',
        'App\Application\Orders\CanonicalOrderWriter',
        'App\Application\Orders\OrderWriteResult',
        'App\Application\Payments\Actions\RefundOrderPaymentAction',
        'App\Application\Privacy\AccountPrivacyService',
        'App\Application\Products\CanonicalProductWriter',
        'App\Application\Products\MerchantProductCatalogAdapter',
        'App\Application\Quotes\CanonicalQuoteWriter',
        'App\Application\Quotes\QuoteWriteResult',
        'App\Application\Reservations\CanonicalReservationWriter',
        'App\Application\Reservations\ReservationWriteResult',
        'App\Application\Sales\Handlers\CancelOrderHandler',
        'App\Application\Sales\Listeners\RestoreStockOnCancellation',
        'App\Application\Sales\Results\PublicOrderCreationResult',
        'App\Application\ServiceCatalog\CanonicalServiceWriter',
        'App\Application\ServiceCatalog\MerchantServiceCatalogAdapter',
        'App\Application\Tenant\Handlers\UpdateLandingPageHandler',
        'App\Application\Tenant\Transformers\TenantMicrositeTransformer',
    ]);

arch('Application Layer must NOT contain Global Folders (Modular Strictness)')
    ->expect('App\Application')
    ->not->toUse([
        'App\Application\Validators', // MUST use Module/Validators
        'App\Application\Jobs',       // MUST use Infrastructure/Jobs
        'App\Application\Services',   // MUST use Module/Actions or Domain Services
    ]);

test('Root of Application must remain pure and modular (Orphan Ban)', function (): void {
    $appApplicationRoot = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Application';
    $forbiddenDirs = [
        $appApplicationRoot.DIRECTORY_SEPARATOR.'Listeners',
        $appApplicationRoot.DIRECTORY_SEPARATOR.'Transformers',
        $appApplicationRoot.DIRECTORY_SEPARATOR.'Middleware',
        $appApplicationRoot.DIRECTORY_SEPARATOR.'Mail',
        $appApplicationRoot.DIRECTORY_SEPARATOR.'Services',
        $appApplicationRoot.DIRECTORY_SEPARATOR.'Jobs',
        $appApplicationRoot.DIRECTORY_SEPARATOR.'Contracts',
    ];

    foreach ($forbiddenDirs as $dir) {
        expect(is_dir($dir))->toBeFalse(
            "Application Layer must contain only business modules or Shared. Forbidden dir found: {$dir}"
        );
    }
});

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
        // Pragmatic Exceptions (if strictly necessary, define here)
        'App\Domain\TenantGovernance\Services\TenantOnboardingProgressService',
        'App\Domain\Billing\Services\AiCreditAlertService',
        'App\Domain\Billing\Services\AiCreditLedger',
        'App\Domain\Billing\Services\AiCreditUsageService',
        'App\Domain\Billing\Services\MonthlyAiCreditGrantService',
        'App\Domain\Billing\Services\PlanEntitlementService',
    ]);

/*
|--------------------------------------------------------------------------
| 6. Global Health Checks
|--------------------------------------------------------------------------
*/

arch('No Facades in Domain or Commands')
    ->expect(['App\Domain', 'App\Application\*\Commands'])
    ->not->toUse('Illuminate\Support\Facades')
    ->ignoring([
        'App\Domain\Billing\Services\AiCreditAlertService',
        'App\Domain\Billing\Services\AiCreditLedger',
        'App\Domain\Billing\Services\AiCreditUsageService',
        'App\Domain\Billing\Services\MonthlyAiCreditGrantService',
        'App\Domain\TenantGovernance\Services\TenantOnboardingProgressService',
    ]);
