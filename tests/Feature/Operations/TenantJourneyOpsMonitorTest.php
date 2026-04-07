<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\TenantJourneyOpsMonitor;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerProfile;
use App\Models\OperationalPlaybookTask;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class TenantJourneyOpsMonitorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_monitor_creates_playbook_when_conversion_journey_degrades(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('journey-monitor-conversion');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'name' => 'Cuenta monitoreada',
            'email' => 'journey-monitor@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'prospect',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        foreach (range(1, 4) as $index) {
            $conversation = CustomerConversation::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_profile_id' => $profile->getKey(),
                'conversation_key' => 'conv-'.$index,
                'source_channel' => 'email',
                'status' => $index === 1 ? 'closed' : 'waiting_customer',
                'priority' => 'high',
                'response_sla_minutes' => 60,
                'opened_at' => now()->subDays(2),
                'last_team_message_at' => now()->subHours(10),
                'response_due_at' => now()->subHours(2),
                'closed_at' => $index === 1 ? now()->subHour() : null,
                'metadata' => [],
            ]);

            CustomerJourneyRun::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenant->getKey(),
                'customer_conversation_id' => $conversation->getKey(),
                'customer_profile_id' => $profile->getKey(),
                'journey_key' => 'prospect',
                'segment_key' => 'prospect',
                'status' => 'active',
                'current_step_key' => 'prospect_clarify_offer',
                'sequence_state' => $index === 1 ? 'waiting_customer' : 'stalled',
                'due_state' => $index === 1 ? 'scheduled' : 'breached',
                'current_step_index' => 1,
                'step_due_at' => now()->subHour(),
                'metadata' => [
                    'current_sequence_key' => 'prospect:prospect_clarify_offer:base',
                    'current_sequence_label' => 'Aclarar propuesta',
                    'lane' => 'follow_up',
                ],
            ]);
        }

        $result = app(TenantJourneyOpsMonitor::class)->syncSignals();

        $this->assertGreaterThanOrEqual(1, (int) $result['conversion']);

        $task = OperationalPlaybookTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('trigger_key', 'journey_conversion_watch')
            ->first();

        $this->assertInstanceOf(OperationalPlaybookTask::class, $task);
        $this->assertStringContainsString('Intervenir secuencia comercial degradada', (string) $task->title);
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
            'business_segment_key' => 'premium_phone_store',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
