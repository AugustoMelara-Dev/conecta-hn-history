<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RemainingDomainsPolicyTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_denies_appointment_management_if_the_guard_rejects_the_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $guard = new RecordingTenantPermissionGuard(false);

        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $policy = new AppointmentPolicy($guard);

        self::assertFalse($policy->create($user));
        self::assertSame($user, $guard->user);
        self::assertSame($tenant, $guard->tenant);
        self::assertSame(Capability::APPOINTMENT_BOOKING, $guard->capability);
        self::assertSame(Permission::APPOINTMENTS_MANAGE, $guard->permission);
    }

    #[Test]
    public function it_allows_appointment_management_if_the_guard_accepts_the_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $guard = new RecordingTenantPermissionGuard(true);

        Filament::shouldReceive('getTenant')->andReturn($tenant);

        $policy = new AppointmentPolicy($guard);

        self::assertTrue($policy->create($user));
        self::assertSame($user, $guard->user);
        self::assertSame($tenant, $guard->tenant);
        self::assertSame(Capability::APPOINTMENT_BOOKING, $guard->capability);
        self::assertSame(Permission::APPOINTMENTS_MANAGE, $guard->permission);
    }
}

final class RecordingTenantPermissionGuard implements TenantPermissionGuardInterface
{
    public ?User $user = null;

    public ?Tenant $tenant = null;

    public ?Capability $capability = null;

    public ?Permission $permission = null;

    public function __construct(private readonly bool $allowed) {}

    public function allows(User $user, Tenant $tenant, Capability $capability, Permission $permission): bool
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->capability = $capability;
        $this->permission = $permission;

        return $this->allowed;
    }
}
