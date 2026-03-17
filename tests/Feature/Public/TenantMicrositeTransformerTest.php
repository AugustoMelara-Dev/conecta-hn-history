<?php

declare(strict_types=1);

use App\Infrastructure\Tenant\Transformers\TenantMicrositeTransformer;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('TenantMicrositeTransformer Premium Profile Fields', function () {
    it('exposes about field for storytelling', function () {
        // Arrange
        $tenant = Tenant::factory()->create([
            'about' => 'Our story began in 2020...',
            'description' => 'Test business',
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result)->toHaveKey('about')
            ->and($result['about'])->toBe('Our story began in 2020...');
    });

    it('exposes structured location object with typed coordinates', function () {
        // Arrange
        $tenant = Tenant::factory()->create([
            'latitude' => 15.4994,
            'longitude' => -88.0250,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result)->toHaveKey('location')
            ->and($result['location'])->toBeArray()
            ->and($result['location']['lat'])->toBe(15.4994)
            ->and($result['location']['lng'])->toBe(-88.0250)
            ->and($result['location']['address'])->toBe('Honduras');
    });

    it('preserves legacy flat latitude/longitude for backward compatibility', function () {
        // Arrange
        $tenant = Tenant::factory()->create([
            'latitude' => 15.4994,
            'longitude' => -88.0250,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result)->toHaveKey('latitude')
            ->and($result)->toHaveKey('longitude')
            ->and($result['latitude'])->toBe(15.4994)
            ->and($result['longitude'])->toBe(-88.0250);
    });

    it('handles null coordinates gracefully in location object', function () {
        // Arrange
        $tenant = Tenant::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);

        // Act
        $result = $transformer->transform($tenant);

        // Assert
        expect($result['location']['lat'])->toBeNull()
            ->and($result['location']['lng'])->toBeNull();
    });
});
