<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Policies\MenuSectionPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\TenantMembershipPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function attachMembership(User $user, Tenant $tenant, Role $role): void
{
    TenantMembership::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'accepted_at' => now(),
    ]);
}

test('manager can view catalog when tenant capability and role permission align', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'business_type' => BusinessType::RETAIL->value,
    ]);

    attachMembership($user, $tenant, Role::MANAGER);
    Filament::shouldReceive('getTenant')->andReturn($tenant);

    expect((new ProductPolicy())->viewAny($user))->toBeTrue();
});

test('retail tenant blocks menu sections even for a manager because the capability is off', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'business_type' => BusinessType::RETAIL->value,
    ]);

    attachMembership($user, $tenant, Role::MANAGER);
    Filament::shouldReceive('getTenant')->andReturn($tenant);

    expect((new MenuSectionPolicy())->viewAny($user))->toBeFalse();
});

test('operator cannot manage staff because the role lacks staff permissions', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'business_type' => BusinessType::RETAIL->value,
    ]);

    attachMembership($user, $tenant, Role::OPERATOR);
    Filament::shouldReceive('getTenant')->andReturn($tenant);

    expect((new TenantMembershipPolicy())->viewAny($user))->toBeFalse()
        ->and((new OrderPolicy())->viewAny($user))->toBeTrue();
});
