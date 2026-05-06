<?php

declare(strict_types=1);

test('domain layer must not depend on filament or livewire', function () {
    expect('App\Domain')
        ->not->toUse('Filament')
        ->not->toUse('Livewire')
        ->not->toUse('Illuminate\Http');
});

test('domain events must be final', function () {
    expect('App\Domain\*\Events')
        ->classes()
        ->toBeFinal();
});

/*
test('domain events must not use eloquent models', function () {
    // This rule is strict: No public properties of type Model in Events
    expect('App\Domain\*\Events')
        ->classes()
        ->not->toHavePublicPropertyOfType('Illuminate\Database\Eloquent\Model');
});
*/
