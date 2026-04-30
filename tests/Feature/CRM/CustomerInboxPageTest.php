<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Enums\TenantRole;
use App\Filament\App\Pages\CustomerInbox;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerConversationMessage;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
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
            'body' => 'Quiero agendar mañana por la tarde.',
            'next_action_at' => now()->addHours(6),
            'response_due_at' => now()->addHours(2),
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.customer-inbox', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Bandeja de respuestas')
            ->assertSee('Ana Castillo')
            ->assertSee('Quiero agendar mañana por la tarde.')
            ->assertSee('Recurrente')
            ->assertSee('Sucursal Norte')
            ->assertSee($user->name)
            ->assertSee('Hilo conversacional')
            ->assertSee('Journey activo')
            ->assertSee('Plantillas productizadas')
            ->assertSee('Notas internas')
            ->assertSee('Carga por canal')
            ->assertSee('Carga por equipo')
            ->assertSee('Carga por owner')
            ->assertSee('Carga por ubicación')
            ->assertSee('Sin owner', false);

        Livewire::test(CustomerInbox::class)
            ->set('selectedScope', 'mine')
            ->set('selectedQueueLane', 'active')
            ->set('selectedChannel', 'whatsapp')
            ->set('selectedAssignmentGroup', 'appointments')
            ->set('selectedSlaState', 'today')
            ->call('routeGroup', $note->getKey(), 'appointments')
            ->call('moveLocation', $note->getKey(), (string) $secondLocation->getKey())
            ->call('reassignOwner', $note->getKey(), (string) $teammate->getKey())
            ->set('draftInternalNote', 'Cliente listo para cierre si confirmamos disponibilidad en sucursal sur.')
            ->call('addInternalNote', $note->getKey());

        $note->refresh();

        $this->assertSame('open', $note->status);
        $this->assertSame((string) $teammate->getKey(), (string) $note->assigned_user_id);
        $this->assertSame((string) $secondLocation->getKey(), (string) $note->location_id);
        $this->assertSame((string) $teammate->getKey(), (string) data_get($note->metadata, 'last_owner_id'));
        $this->assertSame((string) $secondLocation->getKey(), (string) data_get($note->metadata, 'last_location_id'));
        $this->assertSame('appointments', (string) data_get($note->metadata, 'assignment_group'));
        $this->assertNotNull($note->customer_conversation_id);

        $conversation = $note->conversation()->withoutGlobalScopes()->first();
        $task = $note->task()->withoutGlobalScopes()->first();

        $this->assertSame('appointments', (string) data_get($conversation, 'assignment_group'));
        $this->assertSame('appointments', (string) data_get($task, 'assignment_group'));

        $internalNote = CustomerConversationMessage::query()
            ->withoutGlobalScopes()
            ->where('customer_conversation_id', $note->customer_conversation_id)
            ->where('direction', 'internal')
            ->where('message_kind', 'internal_note')
            ->latest('id')
            ->first();

        $this->assertInstanceOf(CustomerConversationMessage::class, $internalNote);
        $this->assertSame('internal', $internalNote->direction);
        $this->assertStringContainsString('disponibilidad en sucursal sur', $internalNote->body);

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

    public function test_customer_inbox_can_send_suggested_reply_and_keep_conversation_selected(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-inbox-template');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Laura Flores',
            'email' => 'laura@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'prospect',
            'loyalty_tier' => 'base',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $note = CustomerNote::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'assigned_user_id' => $user->getKey(),
            'note_type' => 'inbound_reply',
            'conversation_key' => 'conv-prospect-001',
            'status' => 'open',
            'priority' => 'normal',
            'source_channel' => 'email',
            'requires_follow_up' => true,
            'body' => 'Necesito una propuesta para hoy.',
            'next_action_at' => now()->addHours(4),
            'response_due_at' => now()->addHours(2),
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(CustomerInbox::class)
            ->call('selectConversation', $note->getKey())
            ->call('sendSuggestedReply', $note->getKey(), 'close_sale')
            ->assertSet('selectedConversationId', (int) $note->getKey())
            ->assertSee('Laura Flores')
            ->assertSee('Journey activo')
            ->assertSee('Carga por equipo')
            ->assertSee('Ruta actual')
            ->assertSee('Disparo automático estimado');

        $note->refresh();

        $touchpoint = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('customer_profile_id', $profile->getKey())
            ->latest('id')
            ->first();

        $this->assertInstanceOf(CustomerTouchpoint::class, $touchpoint);
        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertSame('close_sale', $touchpoint->template_key);
        $this->assertSame('conv-prospect-001', data_get($touchpoint->metadata, 'conversation_key'));
        $this->assertSame((int) $note->getKey(), (int) data_get($touchpoint->metadata, 'source_note_id'));
        $this->assertSame('pending_customer', $note->status);
        $this->assertFalse($note->requires_follow_up);
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
