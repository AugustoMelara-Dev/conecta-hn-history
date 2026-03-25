<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerJourneyManager;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerConversation;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CustomerJourneyBranchingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_journey_branching_uses_last_touchpoint_outcome_to_change_sequence(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-journey-branch-outcome');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Andrea Soto',
            'email' => 'andrea@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'prospect',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $conversation = CustomerConversation::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'owner_user_id' => $user->getKey(),
            'conversation_key' => 'prospect-click-001',
            'source_channel' => 'email',
            'status' => 'waiting_customer',
            'priority' => 'normal',
            'response_sla_minutes' => 90,
            'opened_at' => now()->subDay(),
            'last_team_message_at' => now()->subHours(14),
            'next_action_at' => now()->subHours(1),
            'response_due_at' => now()->subHours(1),
            'metadata' => [],
        ]);

        CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'customer_conversation_id' => $conversation->getKey(),
            'created_by_user_id' => $user->getKey(),
            'channel' => 'email',
            'direction' => 'outbound',
            'automation_key' => 'conversation_follow_up_reminder',
            'template_key' => 'follow_up_reminder',
            'subject' => 'Seguimiento',
            'message' => 'Seguimiento de propuesta.',
            'recipient_address' => 'andrea@example.com',
            'status' => CustomerTouchpoint::STATUS_SENT,
            'provider' => 'resend',
            'provider_message_id' => 'resend-123',
            'delivery_status' => CustomerTouchpoint::DELIVERY_CLICKED,
            'attempt_count' => 1,
            'retry_limit' => 1,
            'opt_in_snapshot' => true,
            'sent_at' => now()->subHours(13),
            'clicked_at' => now()->subHours(12),
            'metadata' => [
                'journey_step_key' => 'prospect_clarify_offer',
            ],
        ]);

        $run = app(CustomerJourneyManager::class)->sync($conversation, $profile);

        $this->assertSame('prospect_clarify_offer', $run->current_step_key);
        $this->assertSame('close_sale', $run->next_template_key);
        $this->assertSame('clicked_offer', data_get($run->metadata, 'current_branch_key'));
        $this->assertSame('Interes activo', data_get($run->metadata, 'current_branch_label'));
        $this->assertSame('prospect:prospect_clarify_offer:clicked_offer', data_get($run->metadata, 'current_sequence_key'));
    }

    public function test_booked_service_journey_branches_when_recent_no_show_signal_exists(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-journey-branch-no-show');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Marina Paz',
            'email' => 'marina@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'booked_service',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $conversation = CustomerConversation::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'owner_user_id' => $user->getKey(),
            'conversation_key' => 'service-no-show-001',
            'source_channel' => 'email',
            'status' => 'waiting_customer',
            'priority' => 'high',
            'response_sla_minutes' => 90,
            'opened_at' => now()->subDay(),
            'last_team_message_at' => now()->subHours(18),
            'next_action_at' => now()->subHour(),
            'response_due_at' => now()->subHour(),
            'metadata' => [
                'business_signals' => [
                    'appointment_no_show' => [
                        'at' => now()->subHours(2)->toIso8601String(),
                        'payload' => [
                            'appointment_status' => 'no_show',
                        ],
                    ],
                ],
                'last_business_signal' => 'appointment_no_show',
                'last_business_signal_at' => now()->subHours(2)->toIso8601String(),
            ],
        ]);

        $run = app(CustomerJourneyManager::class)->sync($conversation, $profile);

        $this->assertSame('service_reduce_no_show', $run->current_step_key);
        $this->assertSame('service_rebook', $run->next_template_key);
        $this->assertSame('no_show_rescue', data_get($run->metadata, 'current_branch_key'));
        $this->assertSame('booked_service:service_reduce_no_show:no_show_rescue', data_get($run->metadata, 'current_sequence_key'));
    }

    public function test_legal_journey_branches_on_recent_tenant_upgrade_signal(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-journey-branch-upgrade');

        $tenant->forceFill([
            'business_type' => 'service',
            'business_segment_key' => 'corporate_law_firm',
        ])->save();

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Gabriel Mejia',
            'email' => 'gabriel@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'prospect',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $conversation = CustomerConversation::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'owner_user_id' => $user->getKey(),
            'conversation_key' => 'legal-upgrade-001',
            'source_channel' => 'email',
            'status' => 'waiting_customer',
            'priority' => 'normal',
            'response_sla_minutes' => 120,
            'opened_at' => now()->subDay(),
            'last_team_message_at' => now()->subHours(30),
            'next_action_at' => now()->subHours(2),
            'response_due_at' => now()->subHours(2),
            'metadata' => [
                'business_signals' => [
                    'tenant_upgrade' => [
                        'at' => now()->subHours(4)->toIso8601String(),
                        'payload' => [
                            'plan_after_upgrade' => 'pro',
                        ],
                    ],
                ],
                'last_business_signal' => 'tenant_upgrade',
                'last_business_signal_at' => now()->subHours(4)->toIso8601String(),
            ],
        ]);

        $run = app(CustomerJourneyManager::class)->sync($conversation, $profile);

        $this->assertSame('legal_formal_next_step', $run->current_step_key);
        $this->assertSame('final_close_push', $run->next_template_key);
        $this->assertSame('post_upgrade_authority', data_get($run->metadata, 'current_branch_key'));
        $this->assertSame('corporate_law_firm:prospect:legal_formal_next_step:post_upgrade_authority', data_get($run->metadata, 'current_sequence_key'));
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
