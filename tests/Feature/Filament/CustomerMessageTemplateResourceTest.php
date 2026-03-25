<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CustomerMessageTemplateResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_owner_can_open_customer_message_templates_pages(): void
    {
        [$user, $tenant] = $this->makeTenantOwner();

        $this->actingAs($user)
            ->get(route('filament.app.resources.customer-message-templates.index', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Plantillas CRM');

        $this->actingAs($user)
            ->get(route('filament.app.resources.customer-message-templates.create', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Clave')
            ->assertSee('Rubro del negocio')
            ->assertSee('Exigir opt-in de marketing');
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenant = Tenant::factory()
            ->state([
                'user_id' => $user->id,
                'slug' => 'tenant-crm-templates',
                'business_type' => 'retail',
            ])
            ->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
