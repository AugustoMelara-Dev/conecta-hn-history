<?php

declare(strict_types=1);

use App\Application\Security\SensitiveTelemetryGuard;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;

it('excludes current and legacy livewire request payloads from telemetry', function (string $uri): void {
    $entry = IncomingEntry::make(['uri' => $uri, 'payload' => ['components' => []]])
        ->type(EntryType::REQUEST);

    expect(SensitiveTelemetryGuard::shouldExclude($entry))->toBeTrue();
})->with([
    '/livewire/update',
    '/livewire/update?token=opaque',
    '/livewire/message/tenant-resource',
]);

it('keeps ordinary telescope request entries eligible for recording', function (): void {
    $entry = IncomingEntry::make(['uri' => '/admin/tenants'])
        ->type(EntryType::REQUEST);

    expect(SensitiveTelemetryGuard::shouldExclude($entry))->toBeFalse();
});

it('does not exclude non-request entries solely because they mention livewire', function (): void {
    $entry = IncomingEntry::make(['uri' => '/livewire/update'])
        ->type(EntryType::LOG);

    expect(SensitiveTelemetryGuard::shouldExclude($entry))->toBeFalse();
});
