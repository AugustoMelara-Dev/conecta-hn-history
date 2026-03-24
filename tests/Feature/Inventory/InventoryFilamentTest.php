<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

it('confirms the infrastructure and resources compile successfully', function () {
    expect(class_exists(\App\Filament\App\Resources\InventoryLevelResource::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\InventoryMovementResource::class))->toBeTrue();
});
