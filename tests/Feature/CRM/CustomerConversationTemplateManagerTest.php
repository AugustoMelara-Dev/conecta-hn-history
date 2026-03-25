<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerConversationTemplateManager;
use App\Mail\CustomerLifecycleMail;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerMessageTemplate;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class CustomerConversationTemplateManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_segment_templates_resolve_and_send_with_conversation_context(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-template-conversation');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana.crm@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'repeat',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $note = CustomerNote::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'assigned_user_id' => $user->getKey(),
            'note_type' => 'inbound_reply',
            'conversation_key' => 'conv-repeat-001',
            'status' => 'open',
            'priority' => 'high',
            'source_channel' => 'email',
            'requires_follow_up' => true,
            'body' => 'Quiero retomar la compra hoy mismo.',
            'response_due_at' => now()->addHours(3),
        ]);

        $manager = app(CustomerConversationTemplateManager::class);
        $playbook = $manager->forConversation($profile, $note);

        $this->assertSame('repeat', $playbook['segment_key']);
        $this->assertCount(3, $playbook['journey']);
        $this->assertSame('fast_follow_up', $playbook['templates'][0]['key']);
        $this->assertTrue($playbook['templates'][0]['available']);

        $touchpoint = $manager->send($profile, 'close_sale', $note, (string) $user->getKey());

        $note->refresh();

        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertSame('close_sale', $touchpoint->template_key);
        $this->assertSame('conv-repeat-001', data_get($touchpoint->metadata, 'conversation_key'));
        $this->assertSame((int) $note->getKey(), (int) data_get($touchpoint->metadata, 'source_note_id'));
        $this->assertSame('close_sale', data_get($touchpoint->metadata, 'journey_template_key'));
        $this->assertSame('pending_customer', $note->status);
        $this->assertFalse($note->requires_follow_up);
        $this->assertNotNull($note->last_team_message_at);

        Mail::assertSent(CustomerLifecycleMail::class, 1);
    }

    public function test_reactivation_template_requires_marketing_opt_in(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-template-optin');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Carlos Rivera',
            'email' => 'carlos.crm@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'reactivation',
            'lifecycle_stage' => 'dormant',
            'email_opt_in' => true,
            'marketing_opt_in' => false,
        ]);

        $definition = app(CustomerConversationTemplateManager::class)->templateDefinition($profile, 'reactivation_recovery');

        $this->assertFalse($definition['available']);
        $this->assertStringContainsString('marketing', (string) $definition['warning']);
    }

    public function test_tenant_template_override_replaces_default_copy_and_can_disable_a_default_template(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-template-override');

        $tenant->forceFill([
            'business_segment_key' => 'dental_clinic',
        ])->save();

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Paola Sierra',
            'email' => 'paola.override@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'repeat',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        CustomerMessageTemplate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'template_key' => 'close_sale',
            'name' => 'Cierre premium dental',
            'description' => 'Override interno para pacientes de alto valor.',
            'channel' => 'email',
            'customer_segment' => 'repeat',
            'business_segment_key' => 'dental_clinic',
            'automation_key' => 'conversation_close_sale',
            'subject' => 'Tu siguiente tratamiento ya puede quedar reservado',
            'body' => 'Hola {customer_name}, desde {business_name} podemos dejar tu tratamiento reservado hoy mismo.',
            'cta_label' => 'Reservar tratamiento',
            'requires_marketing_opt_in' => false,
            'is_active' => true,
            'priority' => 10,
        ]);

        CustomerMessageTemplate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'template_key' => 'service_rebook',
            'name' => 'Sin rebook automatico',
            'channel' => 'email',
            'automation_key' => 'conversation_service_rebook',
            'body' => 'No usar.',
            'is_active' => false,
            'priority' => 20,
        ]);

        $manager = app(CustomerConversationTemplateManager::class);
        $definition = $manager->templateDefinition($profile, 'close_sale');
        $playbook = $manager->forConversation($profile);

        $this->assertSame('Cierre premium dental', $definition['label']);
        $this->assertSame('Tu siguiente tratamiento ya puede quedar reservado', $definition['subject']);
        $this->assertTrue(collect($playbook['templates'])->contains(fn (array $template): bool => $template['key'] === 'close_sale'));
        $this->assertFalse(collect($playbook['templates'])->contains(fn (array $template): bool => $template['key'] === 'service_rebook'));
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
}
