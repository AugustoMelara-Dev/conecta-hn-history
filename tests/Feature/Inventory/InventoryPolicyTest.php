<?php

declare(strict_types=1);

use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Policies\InventoryLevelPolicy;
use App\Policies\InventoryMovementPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

it('successfully registers and resolves inventory policies', function () {
    expect(class_exists(InventoryLevelPolicy::class))->toBeTrue();
    expect(class_exists(InventoryMovementPolicy::class))->toBeTrue();

    $levelPolicy = Gate::getPolicyFor(InventoryLevel::class);
    expect($levelPolicy)->toBeInstanceOf(InventoryLevelPolicy::class);

    $movementPolicy = Gate::getPolicyFor(InventoryMovement::class);
    expect($movementPolicy)->toBeInstanceOf(InventoryMovementPolicy::class);
});
