<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Access\Enums\Role;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Models\CatalogImportBatch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CatalogImportWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_owner_can_open_workspace_and_resume_latest_batch(): void
    {
        [$owner, $tenant] = $this->tenantOwner('catalog-workspace');
        $batch = new CatalogImportBatch;
        $batch->forceFill([
            'tenant_id' => $tenant->id,
            'created_by' => $owner->id,
            'status' => CatalogImportStatus::NeedsReview,
            'total_items' => 1,
            'processed_items' => 1,
        ])->save();

        $this->actingAs($owner)
            ->get(route('tenant.catalog-imports.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CatalogImports/Workspace')
                ->where('workspace.tenant.id', (string) $tenant->id)
                ->where('workspace.tenant.name', $tenant->name)
                ->where('workspace.available', true)
                ->where('workspace.latest_batch_id', (string) $batch->id)
                ->where('workspace.api_url', '/api/v1/catalog-imports')
                ->has('workspace.categories')
            );
    }

    public function test_semilla_owner_sees_upgrade_state_without_ai_access(): void
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create(['slug' => 'catalog-semilla', 'is_pro' => false]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        $this->actingAs($owner->refresh())
            ->get(route('tenant.catalog-imports.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CatalogImports/Workspace')
                ->where('workspace.available', false)
                ->where('workspace.tenant.is_pro', false)
            );
    }

    public function test_unauthenticated_request_cannot_open_workspace(): void
    {
        $this->get(route('tenant.catalog-imports.index', absolute: false))
            ->assertRedirect(route('login', absolute: false));
    }

    /** @return array{User, Tenant} */
    private function tenantOwner(string $slug): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['slug' => $slug]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner->refresh(), $tenant];
    }
}
