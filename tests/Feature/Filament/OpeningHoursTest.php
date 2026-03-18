<?php

declare(strict_types=1);

use App\Filament\App\Resources\ServiceProviderResource;
use App\Filament\App\Resources\TenantProfileResource;
use App\Filament\App\Resources\TenantProfileResource\RelationManagers\OpeningHoursRelationManager;
use App\Models\OpeningHour;
use App\Models\ServiceProvider;

// ═══════════════════════════════════════════════════════════════════════
// VISUAL SCHEDULE EDITOR - Filament Resources Tests
// ═══════════════════════════════════════════════════════════════════════

describe('OpeningHoursRelationManager', function () {
    it('exists and is properly configured', function () {
        expect(class_exists(OpeningHoursRelationManager::class))->toBeTrue();
    });

    it('has correct relationship name', function () {
        $reflection = new ReflectionClass(OpeningHoursRelationManager::class);
        $property = $reflection->getProperty('relationship');

        expect($property->getDefaultValue())->toBe('openingHours');
    });

    it('has Spanish labels configured', function () {
        $reflection = new ReflectionClass(OpeningHoursRelationManager::class);

        $title = $reflection->getProperty('title');
        $modelLabel = $reflection->getProperty('modelLabel');

        expect($title->getDefaultValue())->toBe('Horario de Atención');
        expect($modelLabel->getDefaultValue())->toBe('Horario');
    });
});

describe('TenantProfileResource Relations', function () {
    it('includes OpeningHoursRelationManager', function () {
        $relations = TenantProfileResource::getRelations();

        expect($relations)->toContain(OpeningHoursRelationManager::class);
    });
});

describe('ServiceProviderResource Schedule Config', function () {
    it('has schedule_config in fillable', function () {
        $provider = new ServiceProvider;

        expect($provider->getFillable())->toContain('schedule_config');
    });

    it('casts schedule_config as array', function () {
        $provider = new ServiceProvider;
        $casts = $provider->getCasts();

        expect($casts)->toHaveKey('schedule_config');
        expect($casts['schedule_config'])->toBe('array');
    });

    it('ServiceProviderResource exists and is configured', function () {
        expect(class_exists(ServiceProviderResource::class))->toBeTrue();
        expect(ServiceProviderResource::getModel())->toBe(ServiceProvider::class);
    });
});

describe('OpeningHour Model', function () {
    it('exists with required fields', function () {
        expect(class_exists(OpeningHour::class))->toBeTrue();

        $model = new OpeningHour;
        $fillable = $model->getFillable();

        expect($fillable)->toContain('day_of_week');
        expect($fillable)->toContain('opening_time');
        expect($fillable)->toContain('closing_time');
        expect($fillable)->toContain('tenant_id');
    });

    it('uses BelongsToTenant trait for isolation', function () {
        $model = new OpeningHour;
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(\App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant::class);
    });
});
