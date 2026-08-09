<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Application\Marketing\CampaignDispatchService;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Jobs\Marketing\SendCampaignEmail;
use App\Mail\CampaignOfferMail;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CampaignDeliveryTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->reset();

        parent::tearDown();
    }

    public function test_campaign_schema_supports_the_active_filament_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('campaigns', [
            'subject',
            'content',
            'scheduled_at',
            'sent_at',
            'recipients_count',
            'delivered_count',
            'failed_count',
            'blocked_count',
            'dispatch_started_at',
            'completed_at',
            'failure_reason',
        ]));
        $this->assertTrue(Schema::hasTable('campaign_deliveries'));
    }

    public function test_worker_sends_uuid_tenant_campaign_only_to_a_current_follower(): void
    {
        app(TenantContext::class)->setAsSystemRoot();
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $follower = User::factory()->create(['email' => 'follower@example.test']);
        $outsider = User::factory()->create(['email' => 'outsider@example.test']);
        $tenant->followers()->attach($follower->id);

        $campaign = Campaign::forceCreate([
            'tenant_id' => $tenant->id,
            'title' => 'Legacy title',
            'subject' => 'Oferta del tenant correcto',
            'content' => '<p>Contenido seguro</p>',
            'type' => 'email',
            'status' => 'draft',
        ]);
        $foreignCampaign = Campaign::forceCreate([
            'tenant_id' => $otherTenant->id,
            'title' => 'Foreign title',
            'subject' => 'Oferta extranjera',
            'content' => '<p>No debe salir</p>',
            'type' => 'email',
            'status' => 'draft',
        ]);

        app(TenantContext::class)->reset();
        app(TenantContext::class)->setTenant((string) $tenant->id);
        Mail::fake();

        $job = new SendCampaignEmail(
            tenantId: (string) $tenant->id,
            campaignId: (int) $campaign->id,
            userId: (string) $follower->id,
        );
        $job->handle();

        Mail::assertSent(CampaignOfferMail::class, function (CampaignOfferMail $mail) use ($follower, $tenant): bool {
            return $mail->hasTo($follower->email)
                && $mail->user->id === (string) $follower->id
                && $mail->campaign->tenantName === $tenant->name
                && $mail->campaign->body === '<p>Contenido seguro</p>';
        });

        Mail::fake();
        (new SendCampaignEmail(
            tenantId: (string) $tenant->id,
            campaignId: (int) $campaign->id,
            userId: (string) $outsider->id,
        ))->handle();
        (new SendCampaignEmail(
            tenantId: (string) $tenant->id,
            campaignId: (int) $foreignCampaign->id,
            userId: (string) $follower->id,
        ))->handle();

        Mail::assertNothingSent();
    }

    public function test_job_identity_contains_tenant_campaign_and_uuid_recipient(): void
    {
        $job = new SendCampaignEmail(
            tenantId: '00000000-0000-4000-8000-000000000010',
            campaignId: 42,
            userId: '00000000-0000-4000-8000-000000000020',
        );

        $this->assertSame(
            'campaign_00000000-0000-4000-8000-000000000010_42_user_00000000-0000-4000-8000-000000000020',
            $job->uniqueId(),
        );
    }

    public function test_due_campaign_is_claimed_once_and_queues_only_its_tenant_followers(): void
    {
        app(TenantContext::class)->setAsSystemRoot();
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $recipient = User::factory()->create(['email' => 'recipient@example.test']);
        $foreignRecipient = User::factory()->create(['email' => 'foreign@example.test']);
        $tenant->followers()->attach($recipient->id);
        $foreignTenant->followers()->attach($foreignRecipient->id);
        $campaign = Campaign::forceCreate([
            'tenant_id' => $tenant->id,
            'title' => 'Oferta programada',
            'subject' => 'Oferta real',
            'content' => '<p>Contenido</p>',
            'type' => 'email',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);
        Queue::fake();

        $first = (new CampaignDispatchService)->dispatchDue();
        $second = (new CampaignDispatchService)->dispatchDue();

        self::assertSame(['claimed' => 1, 'queued' => 1, 'completed_empty' => 0, 'errors' => 0], $first);
        self::assertSame(0, $second['claimed']);
        Queue::assertPushed(SendCampaignEmail::class, 1);
        Queue::assertPushed(SendCampaignEmail::class, fn (SendCampaignEmail $job): bool => $job->tenantId === $tenant->id
            && $job->campaignId === $campaign->id
            && $job->userId === $recipient->id);
        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'tenant_id' => $tenant->id,
            'status' => 'dispatching',
            'recipients_count' => 1,
        ]);
        $this->assertDatabaseMissing('campaign_deliveries', [
            'campaign_id' => $campaign->id,
            'user_id' => $foreignRecipient->id,
        ]);
    }
}
