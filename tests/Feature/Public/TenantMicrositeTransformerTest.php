<?php

declare(strict_types=1);

use App\Application\Tenant\Transformers\TenantMicrositeTransformer;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $context = app(TenantContext::class);
    $context->reset();
    $context->setAsSystemRoot();
});
afterEach(function (): void {
    app(TenantContext::class)->reset();
});

describe('TenantMicrositeTransformer Premium Profile Fields', function () {
    it('exposes about field for storytelling', function () {
        // Arrange
        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create([
            'about' => 'Our story began in 2020...',
            'description' => 'Test business',
        ]));

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result)->toHaveKey('about')
            ->and($result['about'])->toBe('Our story began in 2020...');
    });

    it('exposes structured location object with typed coordinates', function () {
        // Arrange
        $attributes = [
            'latitude' => 15.4994,
            'longitude' => -88.0250,
        ];
        if (Schema::hasColumn('tenants', 'address')) {
            $attributes['address'] = 'Calle Principal, Tegucigalpa';
        }
        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create($attributes));

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result)->toHaveKey('location')
            ->and($result['location'])->toBeArray()
            ->and($result['location']['lat'])->toBe(15.4994)
            ->and($result['location']['lng'])->toBe(-88.0250)
            ->and($result['location']['address'])->toBe(Schema::hasColumn('tenants', 'address') ? 'Calle Principal, Tegucigalpa' : 'Honduras');
    });

    it('preserves legacy flat latitude/longitude for backward compatibility', function () {
        // Arrange
        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create([
            'latitude' => 15.4994,
            'longitude' => -88.0250,
        ]));

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result)->toHaveKey('latitude')
            ->and($result)->toHaveKey('longitude')
            ->and((float) $result['latitude'])->toBe(15.4994)
            ->and((float) $result['longitude'])->toBe(-88.0250);
    });

    it('handles null coordinates gracefully in location object', function () {
        // Arrange
        $tenant = Tenant::withoutEvents(fn () => Tenant::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]));

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result['location']['lat'])->toBeNull()
            ->and($result['location']['lng'])->toBeNull();
    });
});
