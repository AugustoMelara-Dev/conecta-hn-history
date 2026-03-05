<?php

use App\Infrastructure\Identity\Fortify\Actions\Identity\CreateNewUser;
use App\Models\Tenant;
use App\Models\User;
use App\Domain\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;

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
    $this->assertDatabaseHas('users', [
        'email' => 'owner@example.com',
        'tenant_id' => (string) $user->tenant_id,
    ]);

    $this->assertDatabaseHas('tenants', [
        'id' => (string) $user->tenant_id,
        'name' => 'My New Company',
    ]);

    // Check Role
    expect($user->role)->toBe(UserRole::TENANT_OWNER); // 'seller'
});

test('it joins existing tenant as staff when tenant_id provided', function () {
    // Arrange
    $tenant = Tenant::factory()->create();
    $action = app(CreateNewUser::class);
    
    $input = [
        'name' => 'Staff User',
        'email' => 'staff@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => 'on',
        'tenant_id' => $tenant->id,
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

    // Expect Validation Exception provided by Validator::make
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    
    $action->create($input);
});
