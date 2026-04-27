<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Marketing\Jobs\DispatchCampaignSegment;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Marketing\Enums\AudienceType;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class DispatchDueCustomerCampaignsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_due_campaigns_and_rolls_cadence_forward(): void
    {
        Bus::fake();

        [$owner, $tenant] = $this->makeTenantOwner('crm-campaign-cadence');

        $scheduled = Campaign::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'subject' => 'Reactiva base tibia',
            'content' => 'Tenemos novedades para ti.',
            'type' => 'email',
            'audience_type' => AudienceType::CRM_CUSTOMERS,
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(10),
            'recipients_count' => 0,
        ]);

        $recurring = Campaign::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'subject' => 'VIP semanal',
            'content' => 'Tienes beneficios nuevos esta semana.',
            'type' => 'whatsapp',
            'audience_type' => AudienceType::CRM_CUSTOMERS,
            'status' => 'scheduled',
            'is_recurring' => true,
            'cadence_interval_days' => 7,
            'next_run_at' => now()->subMinute(),
            'recipients_count' => 0,
        ]);

        $this->artisan('crm:dispatch-due-campaigns --tenant='.(string) $tenant->getKey().' --limit=10')
            ->assertExitCode(0);

        $scheduled->refresh();
        $recurring->refresh();

        $this->assertSame('queued', $scheduled->status);
        $this->assertNull($scheduled->next_run_at);
        $this->assertNotNull($scheduled->last_dispatched_at);

        $this->assertSame('scheduled', $recurring->status);
        $this->assertNotNull($recurring->last_dispatched_at);
        $this->assertNotNull($recurring->next_run_at);
        $this->assertTrue($recurring->next_run_at->greaterThan(now()->addDays(6)));

        Bus::assertDispatchedTimes(DispatchCampaignSegment::class, 2);
    }

    public function test_it_does_not_advance_campaign_status_when_enqueue_fails(): void
    {
        [$owner, $tenant] = $this->makeTenantOwner('crm-campaign-enqueue-fail');

        $scheduled = Campaign::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'subject' => 'Reactiva base tibia',
            'content' => 'Tenemos novedades para ti.',
            'type' => 'email',
            'audience_type' => AudienceType::CRM_CUSTOMERS,
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(10),
            'recipients_count' => 0,
        ]);

        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldIgnoreMissing();
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('Queue transport unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        try {
            $this->artisan('crm:dispatch-due-campaigns --tenant='.(string) $tenant->getKey().' --limit=10');
            $this->fail('Expected queue transport failure was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Queue transport unavailable', $exception->getMessage());
        }

        $scheduled->refresh();

        $this->assertSame('scheduled', $scheduled->status);
        $this->assertNull($scheduled->last_dispatched_at);
        $this->assertNull($scheduled->next_run_at);
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
