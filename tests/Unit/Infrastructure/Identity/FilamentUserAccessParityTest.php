<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Identity\Models\FilamentUser;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * @return Panel&\Mockery\MockInterface
 */
function appPanel(): Panel
{
    /** @var Panel&\Mockery\MockInterface $panel */
    $panel = \Mockery::mock(Panel::class);
    $panel->shouldReceive('getId')->andReturn(User::PANEL_APP);

    return $panel;
}

test('filament user keeps app panel access parity for accepted tenant memberships', function (): void {
    $owner = User::factory()->create([
        'role' => UserRole::TENANT_OWNER,
    ]);

    $memberTenant = Tenant::factory()->create([
        'user_id' => $owner->id,
    ]);

    $staff = User::factory()->create([
        'role' => UserRole::STAFF,
        'tenant_id' => null,
    ]);

    TenantMembership::query()->create([
        'tenant_id' => $memberTenant->id,
        'user_id' => $staff->id,
        'role' => Role::MANAGER,
        'accepted_at' => now(),
    ]);

    $panel = appPanel();
    $domainUser = User::query()->findOrFail($staff->id);
    $filamentUser = FilamentUser::query()->findOrFail($staff->id);

    expect($domainUser->canAccessPanel($panel))->toBeTrue()
        ->and($filamentUser->canAccessPanel($panel))->toBeTrue()
        ->and($filamentUser->canAccessTenant($memberTenant))->toBeTrue()
        ->and($filamentUser->getTenants($panel)->pluck('id')->all())->toContain($memberTenant->id);
});

test('filament user keeps app panel access parity for pending tenant memberships', function (): void {
    $owner = User::factory()->create([
        'role' => UserRole::TENANT_OWNER,
    ]);

    $pendingTenant = Tenant::factory()->create([
        'user_id' => $owner->id,
    ]);

    $staff = User::factory()->create([
        'role' => UserRole::STAFF,
        'tenant_id' => null,
    ]);

    TenantMembership::query()->create([
        'tenant_id' => $pendingTenant->id,
        'user_id' => $staff->id,
        'role' => Role::MANAGER,
        'accepted_at' => null,
    ]);

    $panel = appPanel();
    $domainUser = User::query()->findOrFail($staff->id);
    $filamentUser = FilamentUser::query()->findOrFail($staff->id);

    expect($domainUser->canAccessPanel($panel))->toBeFalse()
        ->and($filamentUser->canAccessPanel($panel))->toBeFalse()
        ->and($filamentUser->canAccessTenant($pendingTenant))->toBeFalse()
        ->and($filamentUser->getTenants($panel)->pluck('id')->all())->not->toContain($pendingTenant->id);
});
