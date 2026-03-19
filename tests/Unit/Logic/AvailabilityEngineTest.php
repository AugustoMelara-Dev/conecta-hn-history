<?php

declare(strict_types=1);

use App\Models\OpeningHour;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Carbon\Carbon;
use Spatie\OpeningHours\OpeningHours;

describe('Tenant Opening Hours', function () {
    it('returns a Spatie OpeningHours object', function () {
        $tenant = new Tenant;
        $tenant->setRelation('openingHours', collect());

        expect($tenant->getOpeningHours())->toBeInstanceOf(OpeningHours::class);
    });

    it('falls back to default 9-5 M-F when no hours defined', function () {
        $tenant = new Tenant;
        $tenant->setRelation('openingHours', collect());

        $openingHours = $tenant->getOpeningHours();

        expect($openingHours)->toBeInstanceOf(OpeningHours::class);

        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('09:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('17:00');

        $sundayRanges = $openingHours->forDay('sunday');
        expect($sundayRanges)->toBeEmpty();
    });

    it('converts opening hour records to Spatie format', function () {
        $tenant = new Tenant;
        $tenant->setRelation('openingHours', collect([
            makeOpeningHour(dayOfWeek: 1, openingTime: '10:00:00', closingTime: '18:00:00'),
        ]));

        $openingHours = $tenant->getOpeningHours();

        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('10:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('18:00');
    });
});

describe('ServiceProvider Opening Hours', function () {
    it('returns a Spatie OpeningHours object', function () {
        $tenant = new Tenant;
        $tenant->setRelation('openingHours', collect());

        $provider = new ServiceProvider;
        $provider->setRelation('tenant', $tenant);

        expect($provider->getOpeningHours())->toBeInstanceOf(OpeningHours::class);
    });

    it('uses schedule_config when set', function () {
        $provider = new ServiceProvider;
        $provider->forceFill([
            'schedule_config' => [
                'monday' => ['12:00-20:00'],
                'tuesday' => ['12:00-20:00'],
            ],
        ]);

        $openingHours = $provider->getOpeningHours();

        expect($openingHours)->toBeInstanceOf(OpeningHours::class);

        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('12:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('20:00');
    });

    it('falls back to tenant hours when no schedule_config exists', function () {
        $tenant = new Tenant;
        $tenant->setRelation('openingHours', collect([
            makeOpeningHour(dayOfWeek: 1, openingTime: '08:00:00', closingTime: '16:00:00'),
        ]));

        $provider = new ServiceProvider;
        $provider->setRelation('tenant', $tenant);

        $openingHours = $provider->getOpeningHours();

        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('08:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('16:00');
    });
});

describe('Booking Slot Generation', function () {
    it('returns empty ranges for closed days', function () {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-17:00'],
            'tuesday' => ['09:00-17:00'],
        ]);

        $sunday = Carbon::create(2026, 2, 15);
        $ranges = $openingHours->forDate($sunday);

        expect($ranges)->toBeEmpty();
    });
});

function makeOpeningHour(int $dayOfWeek, string $openingTime, string $closingTime): OpeningHour
{
    $openingHour = new OpeningHour;
    $openingHour->forceFill([
        'day_of_week' => $dayOfWeek,
        'opening_time' => $openingTime,
        'closing_time' => $closingTime,
    ]);
    $openingHour->syncOriginal();

    return $openingHour;
}
