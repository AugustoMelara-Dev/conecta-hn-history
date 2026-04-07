<?php

declare(strict_types=1);

use App\Filament\App\Resources\ServiceProviderResource;
use App\Models\OpeningHour;
use App\Models\ServiceProvider;

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
