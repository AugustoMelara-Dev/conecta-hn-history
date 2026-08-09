<?php

use App\Actions\Identity\CreateNewUser;
use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

test('it creates new tenant and owner when no tenant_id provided', function () {
    // Arrange
    $action = app(CreateNewUser::class);
    $input = [
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
        'company_name' => 'My New Company',
    ];

    // Act
    $user = $action->create($input);

    // Assert
    expect($user)->toBeInstanceOf(User::class);

    // Check Database State
    assertDatabaseHas('users', [
        'email' => 'owner@example.com',
        'tenant_id' => (string) $user->tenant_id,
    ]);

    assertDatabaseHas('tenants', [
        'id' => (string) $user->tenant_id,
        'name' => 'My New Company',
    ]);

    // Check Role
    expect($user->role)->toBe(UserRole::TENANT_OWNER); // 'seller'
});

test('it joins existing tenant as staff when tenant_id provided', function () {
    // Arrange
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    $action = app(CreateNewUser::class);

    $input = [
        'name' => 'Staff User',
        'email' => 'staff@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
        'tenant_id' => (string) $tenant->id,
    ];

    // Act
    $user = $action->create($input);

    // Assert
    expect((string) $user->tenant_id)->toBe($tenant->id);

    // Check Role
    expect($user->role)->toBe(UserRole::STAFF); // 'staff'

    // Verify NOT Owner
    expect($user->isTenantOwner())->toBeFalse();
});

test('it validates tenant existence', function () {
    $action = app(CreateNewUser::class);

    $input = [
        'name' => 'Hacker User',
        'email' => 'hacker@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
        'tenant_id' => 'invalid-uuid',
    ];

    expect(fn () => $action->create($input))
        ->toThrow(ValidationException::class);
});
