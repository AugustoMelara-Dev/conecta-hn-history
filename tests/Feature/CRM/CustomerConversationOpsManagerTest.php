<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Mail\CustomerLifecycleMail;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class CustomerConversationOpsManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_reopens_and_tracks_conversation_sla_across_inbound_and_outbound_messages(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-conversation-norte');

        $location = Location::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Sucursal Norte',
            'slug' => 'norte',
            'is_active' => true,
        ]);

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $user->getKey(),
            'location_id' => $location->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'repeat',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profile->getKey(),
            'channel' => 'email',
            'direction' => 'outbound',
            'automation_key' => 'manual_follow_up',
            'subject' => 'Seguimiento',
            'message' => 'Estamos pendientes de tu respuesta.',
            'recipient_address' => 'ana@example.com',
            'status' => CustomerTouchpoint::STATUS_SENT,
            'provider' => 'resend',
            'provider_message_id' => 'email_conversation_1',
            'delivery_status' => CustomerTouchpoint::DELIVERY_SENT,
            'opt_in_snapshot' => true,
            'sent_at' => now()->subMinutes(5),
        ]);

        $ops = app(CustomerConversationOpsManager::class);
        $note = $ops->recordInboundReply(
            $touchpoint,
            'Quiero confirmar la compra esta tarde.',
            now(),
            'email',
            ['provider' => 'resend'],
        );

        $this->assertSame('open', $note->status);
        $this->assertTrue($note->requires_follow_up);
        $this->assertNotNull($note->response_due_at);
        $this->assertSame(1, $note->reply_count);
        $this->assertSame((string) $user->getKey(), (string) $note->assigned_user_id);
        $this->assertSame((string) $location->getKey(), (string) $note->location_id);

        $task = CustomerTask::query()->withoutGlobalScopes()->where('customer_note_id', $note->getKey())->first();
        $this->assertInstanceOf(CustomerTask::class, $task);
        $this->assertSame('open', $task->status);
        $this->assertSame((string) $user->getKey(), (string) $task->assigned_user_id);
        $this->assertSame((string) $location->getKey(), (string) $task->location_id);

        app(CustomerAutomationManager::class)->sendEmailTouchpoint(
            profile: $profile,
            subject: 'Te respondemos hoy',
            body: 'Ya estamos revisando tu caso para responderte hoy mismo.',
            automationKey: 'manual_follow_up',
            actorUserId: (string) $user->getKey(),
        );

        $note->refresh();

        $this->assertSame('pending_customer', $note->status);
        $this->assertFalse($note->requires_follow_up);
        $this->assertSame((string) $user->getKey(), (string) $note->handled_by_user_id);
        $this->assertSame((string) $user->getKey(), (string) $note->assigned_user_id);
        $this->assertNotNull($note->first_response_at);
        $this->assertNotNull($note->last_team_message_at);
        $this->assertSame('waiting_customer', (string) $task->fresh()?->status);

        $reopened = $ops->recordInboundReply(
            $touchpoint->fresh(),
            'Todavia tengo una duda sobre el precio.',
            now()->addHour(),
            'email',
            ['provider' => 'resend'],
        );

        $this->assertSame((int) $note->getKey(), (int) $reopened->getKey());
        $this->assertSame('open', $reopened->status);
        $this->assertTrue($reopened->requires_follow_up);
        $this->assertSame(2, $reopened->reply_count);
        $this->assertNotNull($reopened->response_due_at);
        $this->assertSame((string) $location->getKey(), (string) $reopened->location_id);
        $this->assertSame('open', (string) $task->fresh()?->status);

        Mail::assertSent(CustomerLifecycleMail::class, 1);
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
