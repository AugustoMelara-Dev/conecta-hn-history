<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerConversationTemplateManager;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class DispatchCustomerJourneyStepsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_dispatches_due_automatic_journey_steps_and_advances_the_sequence(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-journey-auto');

        $location = Location::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Sucursal Centro',
            'slug' => 'centro',
            'is_active' => true,
        ]);

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'location_id' => $location->getKey(),
            'name' => 'Marta Rivera',
            'email' => 'marta@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'repeat',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $conversation = CustomerConversation::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'owner_user_id' => $user->getKey(),
            'location_id' => $location->getKey(),
            'assignment_group' => 'sales',
            'conversation_key' => 'journey-repeat-001',
            'source_channel' => 'email',
            'status' => 'waiting_customer',
            'priority' => 'normal',
            'response_sla_minutes' => 90,
            'opened_at' => now()->subDays(2),
            'first_response_at' => now()->subDay(),
            'last_team_message_at' => now()->subHours(26),
            'next_action_at' => now()->subMinutes(10),
            'response_due_at' => now()->addHour(),
            'escalation_due_at' => now()->addHours(2),
            'metadata' => [],
        ]);

        $note = CustomerNote::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'customer_conversation_id' => $conversation->getKey(),
            'assigned_user_id' => $user->getKey(),
            'location_id' => $location->getKey(),
            'note_type' => 'inbound_reply',
            'status' => 'open',
            'priority' => 'normal',
            'source_channel' => 'email',
            'requires_follow_up' => true,
            'body' => 'Quiero retomar la compra esta semana.',
            'conversation_key' => 'journey-repeat-001',
            'response_due_at' => now()->addHour(),
            'last_customer_message_at' => now()->subHours(28),
            'reply_count' => 1,
            'metadata' => [
                'provider_message_id' => 'email.reply.1',
                'provider_thread_key' => 'journey-repeat-001',
                'assignment_group' => 'sales',
                'assignment_group_label' => 'Ventas',
            ],
            'next_action_at' => now()->subMinutes(10),
        ]);

        $steps = app(CustomerConversationTemplateManager::class)->journeyDefinition($profile);
        $currentStep = $steps[1];
        $nextStep = $steps[2];

        $run = CustomerJourneyRun::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_conversation_id' => $conversation->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'journey_key' => 'repeat',
            'segment_key' => 'repeat',
            'status' => 'active',
            'current_step_key' => (string) $currentStep['key'],
            'next_template_key' => (string) $currentStep['template_key'],
            'sequence_state' => 'waiting_customer',
            'due_state' => 'breached',
            'current_step_index' => 1,
            'next_action_at' => now()->subMinutes(10),
            'step_due_at' => now()->subMinutes(5),
            'auto_step_count' => 0,
            'metadata' => [
                'steps' => $steps,
                'current_step' => $currentStep,
                'next_step' => $nextStep,
                'recommended_template_key' => (string) $currentStep['template_key'],
                'lane' => (string) $currentStep['lane'],
                'auto_send' => true,
            ],
        ]);

        $this->artisan('crm:dispatch-journeys --tenant='.(string) $tenant->getKey().' --limit=10')
            ->assertExitCode(0);

        $touchpoint = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->where('customer_conversation_id', $conversation->getKey())
            ->latest('id')
            ->first();

        $this->assertInstanceOf(CustomerTouchpoint::class, $touchpoint);
        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertSame('follow_up_reminder', $touchpoint->template_key);
        $this->assertSame('journey-repeat-001', (string) data_get($touchpoint->metadata, 'provider_thread_key'));
        $this->assertSame('email.reply.1', (string) data_get($touchpoint->metadata, 'provider_reply_to'));

        $run->refresh();
        $conversation->refresh();
        $note->refresh();

        $this->assertSame('repeat_close_continuity', (string) $run->current_step_key);
        $this->assertSame('close_sale', (string) $run->next_template_key);
        $this->assertSame(1, (int) $run->auto_step_count);
        $this->assertSame((int) $touchpoint->getKey(), (int) $run->last_auto_touchpoint_id);
        $this->assertNull($run->current_step_executed_at);
        $this->assertSame('waiting_customer', (string) $run->sequence_state);
        $this->assertSame('waiting_customer', (string) $conversation->status);
        $this->assertSame('pending_customer', (string) $note->status);
        $this->assertFalse((bool) $note->requires_follow_up);
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
