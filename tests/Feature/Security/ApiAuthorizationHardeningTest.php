<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiAuthorizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_update_tenant_settings_with_only_tenant_id_context(): void
    {
        [$tenant, $customer] = $this->makeCustomerAttachedToTenant();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/settings/update', [
                'bank_name' => 'Banco atacante',
                'bank_account' => '000-000-000',
                'whatsapp_number' => '+50499999999',
            ])
            ->assertForbidden();

        $tenant->refresh();

        $this->assertNotSame('Banco atacante', $tenant->bank_name);
        $this->assertNotSame('000-000-000', $tenant->bank_account);
    }

    public function test_customer_cannot_request_sales_reports_with_only_tenant_id_context(): void
    {
        [, $customer] = $this->makeCustomerAttachedToTenant();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/reports/sales/pdf')
            ->assertForbidden();
    }

    public function test_settings_rejects_explicit_tenant_header_without_membership(): void
    {
        [$tenantA, $owner] = $this->makeOwnerAttachedToTenant('owner-a@example.test', 'tenant-header-a');
        $tenantB = Tenant::factory()->create([
            'slug' => 'tenant-header-b',
            'business_type' => 'retail',
            'bank_name' => 'Banco original',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Tenant-ID', (string) $tenantB->getKey())
            ->postJson('/api/v1/settings/update', [
                'bank_name' => 'Banco atacante',
                'bank_account' => '111-222',
                'whatsapp_number' => '+50499998888',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantB->id,
            'bank_name' => 'Banco original',
        ]);
        $this->assertDatabaseMissing('tenants', [
            'id' => $tenantA->id,
            'bank_name' => 'Banco atacante',
        ]);
    }

    public function test_reports_reject_explicit_tenant_header_without_membership(): void
    {
        [, $owner] = $this->makeOwnerAttachedToTenant('report-owner@example.test', 'report-tenant-a');
        $tenantB = Tenant::factory()->create([
            'slug' => 'report-tenant-b',
            'business_type' => 'retail',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Tenant-ID', (string) $tenantB->getKey())
            ->getJson('/api/v1/reports/sales/pdf')
            ->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function makeCustomerAttachedToTenant(): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'email_verified_at' => now(),
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'security-hardening-tenant',
            'business_type' => 'retail',
        ]);

        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'email_verified_at' => now(),
        ]);

        $customer->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        return [$tenant, $customer];
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function makeOwnerAttachedToTenant(string $email, string $slug): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => $slug,
            'business_type' => 'retail',
        ]);

        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        return [$tenant, $owner];
    }
}
