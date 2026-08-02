<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Access\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WorkforceActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workforce_cockpit_is_tenant_protected_and_reports_only_evidence_states(): void
    {
        [$owner, $tenant] = $this->tenantOwner();

        $this->get(route('tenant.workforce.show', absolute: false))
            ->assertRedirect(route('login', absolute: false));

        $this->actingAs($owner)
            ->get(route('tenant.workforce.show', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Workforce')
                ->where('workforce.tenant.id', (string) $tenant->id)
                ->where('workforce.live_evidence.verified', false)
                ->has('workforce.roles')
                ->has('workforce.channels')
            );
    }

    /** @return array{User, Tenant} */
    private function tenantOwner(): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner->refresh(), $tenant];
    }
}
