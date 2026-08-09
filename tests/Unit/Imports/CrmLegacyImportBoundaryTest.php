<?php

declare(strict_types=1);

use App\Application\Imports\Imports\CompaniesImport;
use App\Application\Imports\Imports\ContactsImport;
use App\Application\Imports\ImportService;
use App\Infrastructure\Imports\ExcelImporter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

it('keeps CRM legacy imports fail-closed at the Excel constructor boundary', function (): void {
    Bus::fake();
    Event::fake();

    $csv = tempnam(sys_get_temp_dir(), 'dovra-crm-import-boundary-');
    expect($csv)->not->toBeFalse();
    file_put_contents($csv, "name,first_name,last_name,email\nblocked,Grace,Hopper,grace@example.test\n");

    try {
        foreach ([ContactsImport::class, CompaniesImport::class] as $importClass) {
            $result = (new ExcelImporter)->import($importClass, $csv);

            expect($result['success'])->toBeFalse()
                ->and($result['imported'])->toBe(0)
                ->and($result['errors'])->not->toBeEmpty()
                ->and(implode(' ', $result['errors']))->toMatch('/argument|parameter|tenant/i');
        }

        Bus::assertNothingDispatched();
        Event::assertNothingDispatched();
    } finally {
        @unlink($csv);
    }
});

it('does not advertise a deleted DealsImport through the legacy import registry', function (): void {
    $service = new ImportService;

    expect($service->entities())->not->toContain('deals')
        ->and(class_exists('App\\Application\\Imports\\Imports\\DealsImport', false))->toBeFalse();
});
