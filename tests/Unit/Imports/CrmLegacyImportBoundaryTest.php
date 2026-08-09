<?php

declare(strict_types=1);

use App\Application\Imports\Imports\CompaniesImport;
use App\Application\Imports\Imports\ContactsImport;
use App\Application\Imports\ImportService;
use App\Infrastructure\Imports\ExcelImporter;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Maatwebsite\Excel\Facades\Excel;

it('keeps CRM legacy imports fail-closed before the Excel facade can execute', function (): void {
    Bus::fake();
    Event::fake();
    Excel::shouldReceive('import')->never();

    $csv = tempnam(sys_get_temp_dir(), 'dovra-crm-import-boundary-');
    expect($csv)->not->toBeFalse();
    file_put_contents($csv, "name,first_name,last_name,email\nblocked,Grace,Hopper,grace@example.test\n");

    try {
        foreach ([ContactsImport::class, CompaniesImport::class] as $importClass) {
            $constructor = (new ReflectionClass($importClass))->getConstructor();
            expect($constructor)->not->toBeNull()
                ->and($constructor->getNumberOfRequiredParameters())->toBe(1);

            $result = (new ExcelImporter)->import($importClass, $csv);

            expect($result['success'])->toBeFalse()
                ->and($result['imported'])->toBe(0)
                ->and($result['errors'])->not->toBeEmpty()
                ->and(implode(' ', $result['errors']))->toMatch('/argument|parameter|tenant/i');
        }

        Bus::assertNothingDispatched();
        $events = Event::dispatchedEvents();
        $unexpectedEvents = array_diff_key($events, [ConnectionEstablished::class => true]);
        expect($unexpectedEvents)->toBeEmpty('no import/domain event may be reported on constructor failure');
    } finally {
        @unlink($csv);
    }
});

it('keeps the deleted DealsImport absent and unadvertised', function (): void {
    $service = new ImportService;
    $dealsClass = 'App\\Application\\Imports\\Imports\\DealsImport';
    $dealsPath = base_path('app/Application/Imports/Imports/DealsImport.php');
    $autoloadWarning = null;

    set_error_handler(static function (int $severity, string $message) use (&$autoloadWarning): bool {
        $autoloadWarning = $message;

        return true;
    });
    try {
        $loaded = class_exists($dealsClass, true);
    } finally {
        restore_error_handler();
    }

    expect(is_file($dealsPath))->toBeFalse()
        ->and($loaded)->toBeFalse()
        ->and($service->entities())->not->toContain('deals');
});
