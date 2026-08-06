<?php

declare(strict_types=1);

use App\Application\Imports\ImportService;

it('ImportService has 8 entities registered', function () {
    $service = new ImportService();
    $entities = $service->entities();

    expect($entities)->toContain('contacts');
    expect($entities)->toContain('companies');
    expect($entities)->toContain('deals');
    expect($entities)->toContain('products');
    expect($entities)->toContain('orders');
    expect($entities)->toContain('users');
    expect($entities)->toContain('coupons');
    expect($entities)->toContain('appointments');
    expect(count($entities))->toBeGreaterThanOrEqual(8);
});

it('ImportService throws for unknown entity', function () {
    $service = new ImportService();
    $service->getImporterClass('unknown');
})->throws(InvalidArgumentException::class);

it('ImportService templates provide headers and sample row', function () {
    $service = new ImportService();
    $templates = $service->templates();

    expect($templates['contacts']['headers'])->toContain('first_name');
    expect($templates['contacts']['headers'])->toContain('email');
    expect($templates['contacts']['sample'])->toHaveKey('first_name');
});

it('ImportService can register custom entities', function () {
    $service = new ImportService();
    $service->register('custom', \App\Application\Imports\Imports\ContactsImport::class);

    expect($service->has('custom'))->toBeTrue();
    expect($service->getImporterClass('custom'))->toBe(\App\Application\Imports\Imports\ContactsImport::class);
});

it('Each Import class implements Importable interface', function () {
    $service = new ImportService();

    foreach ($service->entities() as $entity) {
        $class = $service->getImporterClass($entity);
        $instance = app(ImportService::class)->makeImporter($entity, '');
        expect($instance)->toBeInstanceOf(\App\Application\Imports\Concerns\Importable::class);
    }
});
