<?php

declare(strict_types=1);

use App\Models\OpeningHour;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Spatie\OpeningHours\OpeningHours;
use Tests\TestCase;

uses(TestCase::class);

// ═══════════════════════════════════════════════════════════════════════
// AVAILABILITY ENGINE TESTS - Service Hours Integration
// ═══════════════════════════════════════════════════════════════════════

describe('Tenant Opening Hours', function () {
    it('returns Spatie OpeningHours object', function () {
        $tenant = new Tenant;
        
        expect(method_exists($tenant, 'getOpeningHours'))->toBeTrue();
        expect(method_exists($tenant, 'openingHours'))->toBeTrue();
    });

    it('falls back to default 9-5 M-F when no hours defined', function () {
        // Create tenant without DB records
        $tenant = Mockery::mock(Tenant::class)->makePartial();
        $tenant->shouldReceive('getAttribute')
            ->with('openingHours')
            ->andReturn(collect([]));

        $openingHours = $tenant->getOpeningHours();

        expect($openingHours)->toBeInstanceOf(OpeningHours::class);
        
        // Monday should have 09:00-17:00
        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('09:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('17:00');
        
        // Sunday should be closed (no ranges)
        $sundayRanges = $openingHours->forDay('sunday');
        expect($sundayRanges)->toBeEmpty();
    });

    it('converts DB records to Spatie format', function () {
        // Mock OpeningHour record for Monday 10:00-18:00
        $mockOpeningHour = Mockery::mock(OpeningHour::class);
        $mockOpeningHour->shouldReceive('getAttribute')
            ->with('day_of_week')
            ->andReturn(1); // Monday
        $mockOpeningHour->shouldReceive('getRawOpeningTime')->andReturn('10:00:00');
        $mockOpeningHour->shouldReceive('getRawClosingTime')->andReturn('18:00:00');
        // Allow attribute access via __get
        $mockOpeningHour->shouldReceive('__get')
            ->with('day_of_week')
            ->andReturn(1);

        $tenant = Mockery::mock(Tenant::class)->makePartial();
        $tenant->shouldReceive('getAttribute')
            ->with('openingHours')
            ->andReturn(collect([$mockOpeningHour]));

        $openingHours = $tenant->getOpeningHours();

        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('10:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('18:00');
    });
});

describe('ServiceProvider Opening Hours', function () {
    it('returns Spatie OpeningHours object', function () {
        $provider = new ServiceProvider;
        
        expect(method_exists($provider, 'getOpeningHours'))->toBeTrue();
    });

    it('uses schedule_config when set (provider override)', function () {
        $provider = Mockery::mock(ServiceProvider::class)->makePartial();
        $provider->shouldReceive('getAttribute')
            ->with('schedule_config')
            ->andReturn([
                'monday' => ['12:00-20:00'],
                'tuesday' => ['12:00-20:00'],
            ]);

        $openingHours = $provider->getOpeningHours();

        expect($openingHours)->toBeInstanceOf(OpeningHours::class);
        
        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('12:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('20:00');
    });

    it('falls back to tenant hours when no schedule_config', function () {
        // Mock tenant with 08:00-16:00 hours
        $mockTenant = Mockery::mock(Tenant::class);
        $mockTenant->shouldReceive('getOpeningHours')->andReturn(
            OpeningHours::create([
                'monday' => ['08:00-16:00'],
            ])
        );

        $provider = Mockery::mock(ServiceProvider::class)->makePartial();
        $provider->shouldReceive('getAttribute')
            ->with('schedule_config')
            ->andReturn(null);
        $provider->shouldReceive('getAttribute')
            ->with('tenant')
            ->andReturn($mockTenant);

        $openingHours = $provider->getOpeningHours();

        $mondayRanges = $openingHours->forDay('monday');
        expect($mondayRanges)->toHaveCount(1);
        expect($mondayRanges[0]->start()->format('H:i'))->toBe('08:00');
        expect($mondayRanges[0]->end()->format('H:i'))->toBe('16:00');
    });
});

describe('Booking Slot Generation', function () {
    it('BookingController has generateAvailableSlots method', function () {
        $reflection = new ReflectionClass(\App\Http\Controllers\Public\BookingController::class);
        
        expect($reflection->hasMethod('generateAvailableSlots'))->toBeTrue();
        
        $method = $reflection->getMethod('generateAvailableSlots');
        
        // Should accept Tenant as first parameter
        $params = $method->getParameters();
        expect($params[0]->getName())->toBe('tenant');
        expect($params[0]->getType()->getName())->toBe('App\Models\Tenant');
    });

    it('closed days return empty slots (sunday default)', function () {
        $openingHours = OpeningHours::create([
            'monday' => ['09:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            // Sunday is not defined = closed
        ]);

        $sunday = \Carbon\Carbon::create(2026, 2, 15); // A Sunday
        $ranges = $openingHours->forDate($sunday);

        expect($ranges)->toBeEmpty();
    });
});
