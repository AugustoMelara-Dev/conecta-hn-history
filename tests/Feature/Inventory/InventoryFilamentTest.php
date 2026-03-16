<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('confirms the infrastructure and resources compile successfully', function () {
    expect(class_exists(\App\Filament\Resources\InventoryLevelResource::class))->toBeTrue();
    expect(class_exists(\App\Filament\Resources\InventoryMovementResource::class))->toBeTrue();
});

