<?php

namespace Tests\Architecture;

use Illuminate\Contracts\Queue\ShouldQueue;

test('Domain jobs implement ShouldQueue')
    ->expect('App\Domain')
    ->classes()
    ->that->implements(ShouldQueue::class)
    ->toBeGroups([
        'App\Domain\AIEnrichment\Jobs',
        'App\Domain\ReportingAndExports\Jobs',
        'App\Domain\TenantGovernance\Jobs',
        'App\Domain\Analytics\Jobs',
        'App\Domain\Marketing\Jobs',
        'App\Domain\MediaProcessing\Jobs',
    ]);

test('Domain jobs are final')
    ->expect('App\Domain')
    ->classes()
    ->that->implements(ShouldQueue::class)
    ->toBeFinal();

test('Domain jobs do not have public properties that are Models')
    ->expect('App\Domain')
    ->classes()
    ->that->implements(ShouldQueue::class)
    ->not->toHavePublicProperty()
    ->matching(function ($property) {
        $type = (string) $property->getType();
        return str_contains($type, 'Model');
    });

test('Domain jobs do not use Facades directly')
    ->expect('App\Domain')
    ->classes()
    ->that->implements(ShouldQueue::class)
    ->not->toUse([
        'Illuminate\Support\Facades\Log',
        'Illuminate\Support\Facades\Storage',
        'Illuminate\Support\Facades\Mail',
        'Barryvdh\DomPDF\Facade\Pdf',
        'Maatwebsite\Excel\Facades\Excel',
    ]);
