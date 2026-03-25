<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Enums\TenantRole;
use App\Filament\App\Pages\CustomerInbox;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

final class CustomerInboxPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_inbox_renders_open_replies_and_allows_follow_up_workflow(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-inbox-norte');

        $teammate = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        TenantUser::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $teammate->getKey(),
            'role' => TenantRole::MANAGER->value,
        ]);

        $location = Location::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Sucursal Norte',
            'slug' => 'norte',
            'is_active' => true,
        ]);

        $secondLocation = Location::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Sucursal Sur',
            'slug' => 'sur',
            'is_active' => true,
        ]);

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'location_id' => $location->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana@example.com',
            'phone' => '9999-1111',
            'preferred_channel' => 'whatsapp',
            'customer_segment' => 'repeat',
            'loyalty_tier' => 'silver',
            'whatsapp_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $note = CustomerNote::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'assigned_user_id' => $user->getKey(),
            'location_id' => $location->getKey(),
            'note_type' => 'inbound_reply',
            'status' => 'open',
            'priority' => 'high',
            'source_channel' => 'whatsapp',
            'requires_follow_up' => true,
            'body' => 'Quiero agendar manana por la tarde.',
            'next_action_at' => now()->addHours(6),
            'response_due_at' => now()->addHours(2),
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.customer-inbox', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Bandeja de respuestas')
            ->assertSee('Ana Castillo')
            ->assertSee('Quiero agendar manana por la tarde.')
            ->assertSee('Recurrente')
            ->assertSee('Sucursal Norte')
            ->assertSee($user->name)
            ->assertSee('Sin owner', false);

        Livewire::test(CustomerInbox::class)
            ->set('selectedScope', 'mine')
            ->set('selectedSlaState', 'today')
            ->call('moveLocation', $note->getKey(), (string) $secondLocation->getKey())
            ->call('reassignOwner', $note->getKey(), (string) $teammate->getKey());

        $note->refresh();

        $this->assertSame('open', $note->status);
        $this->assertSame((string) $teammate->getKey(), (string) $note->assigned_user_id);
        $this->assertSame((string) $secondLocation->getKey(), (string) $note->location_id);
        $this->assertSame((string) $teammate->getKey(), (string) data_get($note->metadata, 'last_owner_id'));
        $this->assertSame((string) $secondLocation->getKey(), (string) data_get($note->metadata, 'last_location_id'));

        Livewire::test(CustomerInbox::class)
            ->call('take', $note->getKey())
            ->call('resolve', $note->getKey());

        $note->refresh();

        $this->assertSame('resolved', $note->status);
        $this->assertSame((string) $user->getKey(), (string) $note->handled_by_user_id);
        $this->assertSame((string) $user->getKey(), (string) $note->assigned_user_id);
        $this->assertSame((string) $secondLocation->getKey(), (string) $note->location_id);
        $this->assertFalse($note->requires_follow_up);
        $this->assertNotNull($note->handled_at);
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenant = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }

    private function setAppPanelTenantContext(Tenant $tenant): void
    {
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAsTenant((string) $tenant->getKey());
    }
}
