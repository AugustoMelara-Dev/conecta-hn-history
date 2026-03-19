<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Identity\Fortify\Actions\Identity\CreateNewUser;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_new_tenant_and_owner_when_no_tenant_id_is_provided(): void
    {
        $action = app(CreateNewUser::class);

        /** @var array<string, string> $input */
        $input = [
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => 'on',
            'company_name' => 'My New Company',
        ];

        $created = $action->create($input);

        self::assertInstanceOf(User::class, $created);

        /** @var User $user */
        $user = $created;

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'tenant_id' => (string) $user->tenant_id,
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => (string) $user->tenant_id,
            'name' => 'My New Company',
        ]);

        self::assertSame(UserRole::TENANT_OWNER, $user->role);
    }

    #[Test]
    public function it_joins_an_existing_tenant_as_staff_when_tenant_id_is_provided(): void
    {
        $tenant = Tenant::factory()->create();
        $action = app(CreateNewUser::class);

        /** @var array<string, string> $input */
        $input = [
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => 'on',
            'tenant_id' => (string) $tenant->id,
        ];

        $created = $action->create($input);

        self::assertInstanceOf(User::class, $created);

        /** @var User $user */
        $user = $created;

        self::assertSame((string) $tenant->id, (string) $user->tenant_id);
        self::assertSame(UserRole::STAFF, $user->role);
        self::assertFalse($user->isTenantOwner());
    }

    #[Test]
    public function it_validates_tenant_existence(): void
    {
        $action = app(CreateNewUser::class);

        /** @var array<string, string> $input */
        $input = [
            'name' => 'Hacker User',
            'email' => 'hacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => 'on',
            'tenant_id' => 'invalid-uuid',
        ];

        $this->expectException(ValidationException::class);

        $action->create($input);
    }
}
