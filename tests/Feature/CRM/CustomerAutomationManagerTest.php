<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Mail\CustomerLifecycleMail;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class CustomerAutomationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_email_follow_up_is_sent_and_logged(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-automation-cafe');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana@example.com',
            'phone' => '9999-1111',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 30,
        ]);

        $touchpoint = app(CustomerAutomationManager::class)->sendEmailTouchpoint(
            profile: $profile,
            subject: 'Seguimiento de Cafe Norte',
            body: 'Queremos ayudarte a volver a comprar.',
            automationKey: 'manual_follow_up',
            actorUserId: (string) $user->getKey(),
        );

        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertNotNull($touchpoint->sent_at);
        $this->assertSame('ana@example.com', $touchpoint->recipient_address);
        $this->assertNotNull($profile->fresh()->last_contacted_at);

        Mail::assertSent(CustomerLifecycleMail::class, function (CustomerLifecycleMail $mail): bool {
            return $mail->hasTo('ana@example.com');
        });
    }

    public function test_reactivation_batch_respects_marketing_opt_in_and_logs_outcomes(): void
    {
        Mail::fake();

        [$user, $tenant] = $this->makeTenantOwner('crm-automation-reactivation');

        CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente Activo',
            'email' => 'activo@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'reactivation',
            'lifecycle_stage' => 'dormant',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
            'reactivation_score' => 92,
        ]);

        CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente Sin Marketing',
            'email' => 'sin-marketing@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'reactivation',
            'lifecycle_stage' => 'dormant',
            'email_opt_in' => true,
            'marketing_opt_in' => false,
            'reactivation_score' => 88,
        ]);

        $result = app(CustomerAutomationManager::class)->runSuggestedAutomationBatch(
            tenant: $tenant,
            automationKey: 'reactivation_email',
            actorUserId: (string) $user->getKey(),
            limit: 10,
        );

        $this->assertSame([
            'processed' => 2,
            'sent' => 1,
            'skipped' => 1,
            'failed' => 0,
        ], $result);

        $this->assertSame(2, CustomerTouchpoint::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, CustomerTouchpoint::query()->withoutGlobalScopes()->where('status', CustomerTouchpoint::STATUS_SENT)->count());
        $this->assertSame(1, CustomerTouchpoint::query()->withoutGlobalScopes()->where('status', CustomerTouchpoint::STATUS_SKIPPED)->count());

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
