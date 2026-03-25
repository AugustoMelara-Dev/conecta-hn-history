<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerMessagingGateway;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class RetryCustomerTouchpointsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_retries_due_touchpoints_for_a_tenant(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-retry-command');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Paola Rivera',
            'email' => 'paola@example.com',
            'preferred_channel' => 'email',
            'customer_segment' => 'active',
            'lifecycle_stage' => 'active',
            'email_opt_in' => true,
            'marketing_opt_in' => true,
        ]);

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_profile_id' => (int) $profile->getKey(),
            'created_by_user_id' => (string) $user->getKey(),
            'channel' => 'email',
            'direction' => 'outbound',
            'automation_key' => 'manual_follow_up',
            'template_key' => 'manual_follow_up',
            'subject' => 'Seguimiento',
            'message' => 'Queremos ayudarte a volver.',
            'recipient_address' => 'paola@example.com',
            'status' => CustomerTouchpoint::STATUS_FAILED,
            'delivery_status' => CustomerTouchpoint::DELIVERY_FAILED,
            'attempt_count' => 1,
            'retry_limit' => 2,
            'opt_in_snapshot' => true,
            'last_attempt_at' => now()->subHour(),
            'failed_at' => now()->subHour(),
            'next_retry_at' => now()->subMinute(),
            'metadata' => [
                'daily_limit' => 1,
                'retry_backoff_minutes' => 30,
            ],
        ]);

        $gateway = \Mockery::mock(CustomerMessagingGateway::class);
        $gateway->shouldReceive('sendLifecycleEmail')
            ->once()
            ->andReturn([
                'provider' => 'resend',
                'provider_message_id' => 'retry_cmd_123',
                'delivery_status' => 'sent',
                'payload' => ['last_event' => 'sent'],
            ]);

        $this->app->instance(CustomerMessagingGateway::class, $gateway);

        $this->artisan('crm:retry-touchpoints', [
            '--tenant' => (string) $tenant->getKey(),
            '--limit' => 10,
        ])
            ->expectsOutputToContain('Reintentos procesados: 1. Exitosos: 1. Siguen pendientes: 0.')
            ->assertExitCode(0);

        $touchpoint->refresh();

        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertNull($touchpoint->next_retry_at);
        $this->assertSame(2, $touchpoint->attempt_count);
        $this->assertSame('retry_cmd_123', $touchpoint->provider_message_id);
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
