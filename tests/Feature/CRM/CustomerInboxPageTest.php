<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Pages\CustomerInbox;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\Tenant;
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

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
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
            'note_type' => 'inbound_reply',
            'status' => 'open',
            'priority' => 'high',
            'source_channel' => 'whatsapp',
            'requires_follow_up' => true,
            'body' => 'Quiero agendar mañana por la tarde.',
            'next_action_at' => now()->addHours(6),
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.customer-inbox', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Bandeja de respuestas')
            ->assertSee('Ana Castillo')
            ->assertSee('Quiero agendar mañana por la tarde.')
            ->assertSee('Recurrente');

        Livewire::test(CustomerInbox::class)
            ->call('take', $note->getKey())
            ->call('resolve', $note->getKey());

        $note->refresh();

        $this->assertSame('resolved', $note->status);
        $this->assertSame((string) $user->getKey(), (string) $note->handled_by_user_id);
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
