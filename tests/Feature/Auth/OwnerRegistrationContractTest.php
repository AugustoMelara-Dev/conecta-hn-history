<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Models\Category;
use App\Models\City;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OwnerRegistrationContractTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->seedGeneralSettings();
        $this->app->bind(\App\Domain\Shared\OutboxRepositoryInterface::class, fn () => new class implements \App\Domain\Shared\OutboxRepositoryInterface
        {
            public function append(object $event): void
            {
                //
            }

            public function retrieveNextStaleBatch(int $batchSize, int $staleThresholdSeconds, array $ownedPartitionKeys): Collection
            {
                return collect();
            }
        });
    }

    public function test_register_route_redirects_to_canonical_wizard(): void
    {
        $this->get('/register')
            ->assertRedirect(route('tenant.register'));
    }

    public function test_register_post_redirects_to_canonical_wizard(): void
    {
        $this->postWithCsrf('/register', [])
            ->assertRedirect(route('tenant.register'));
    }

    public function test_tenant_registration_requires_commercial_mode_and_legal_acceptance(): void
    {
        [$city, $category] = $this->referenceData();

        $payload = [
            'business_name' => 'Clinica Central',
            'slug' => 'clinica-central',
            'name' => 'Owner Test',
            'email' => 'owner.contract@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'selected_plan' => 'free',
        ];

        $this->postWithCsrf(route('tenant.register.store'), $payload)
            ->assertSessionHasErrors(['commercial_mode', 'terms_accepted', 'privacy_accepted']);
    }

    public function test_products_registration_persists_derived_retail_contract(): void
    {
        [$city, $category] = $this->referenceData();

        $this->postWithCsrf(route('tenant.register.store'), $this->validPayload([
            'business_name' => 'Farmacia Central',
            'slug' => 'farmacia-central',
            'email' => 'farmacia.contract@example.com',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'commercial_mode' => CommercialMode::PRODUCTS->value,
            'rubro_key' => 'retail-core',
        ]))->assertRedirect('/app/farmacia-central');

        $tenant = Tenant::query()->where('slug', 'farmacia-central')->firstOrFail();

        $this->assertSame(BusinessType::RETAIL, $tenant->business_type);
        $this->assertSame(CommercialMode::PRODUCTS, $tenant->commercial_mode);
        $this->assertSame($category->id, $tenant->category_id);
        $this->assertSame($city->id, $tenant->city_id);
        $this->assertSame('+504 9999-0000', $tenant->whatsapp_number);
        $this->assertSame('retail-core', $tenant->rubro_key);
    }

    public function test_pro_registration_creates_pending_payment_without_pro_entitlement(): void
    {
        [$city, $category] = $this->referenceData();

        $this->postWithCsrf(route('tenant.register.store'), $this->validPayload([
            'business_name' => 'Plan Pendiente',
            'slug' => 'plan-pendiente',
            'email' => 'plan.pendiente@example.com',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'commercial_mode' => CommercialMode::PRODUCTS->value,
            'selected_plan' => 'pro',
        ]))->assertRedirect('/app/plan-pendiente');

        $tenant = Tenant::query()->where('slug', 'plan-pendiente')->firstOrFail();

        $this->assertFalse((bool) $tenant->is_pro);
        $this->assertSame('semilla', $tenant->plan_type?->value ?? $tenant->plan_type);
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertSame(10, (int) $tenant->product_limit);

        $payment = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending_activation', $payment->bank);
        $this->assertSame('pending', $payment->proof_path);
        $this->assertSame(500.0, (float) $payment->amount);
    }

    public function test_appointments_registration_persists_derived_service_contract(): void
    {
        [$city, $category] = $this->referenceData();

        $this->postWithCsrf(route('tenant.register.store'), $this->validPayload([
            'business_name' => 'Clinica Agenda',
            'slug' => 'clinica-agenda',
            'email' => 'agenda.contract@example.com',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'commercial_mode' => CommercialMode::APPOINTMENTS->value,
            'rubro_key' => 'service-core',
        ]))->assertRedirect('/app/clinica-agenda');

        $tenant = Tenant::query()->where('slug', 'clinica-agenda')->firstOrFail();

        $this->assertSame(BusinessType::SERVICE, $tenant->business_type);
        $this->assertSame(CommercialMode::APPOINTMENTS, $tenant->commercial_mode);
        $this->assertSame('service-core', $tenant->rubro_key);
    }

    public function test_consulting_leads_registration_persists_service_without_catalog_mode(): void
    {
        [$city, $category] = $this->referenceData();

        $this->postWithCsrf(route('tenant.register.store'), $this->validPayload([
            'business_name' => 'Bufete Legal Norte',
            'slug' => 'bufete-legal-norte',
            'email' => 'legal.contract@example.com',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'commercial_mode' => CommercialMode::CONSULTING_LEADS->value,
            'rubro_key' => 'consulting-core',
        ]))->assertRedirect('/app/bufete-legal-norte');

        $tenant = Tenant::query()->where('slug', 'bufete-legal-norte')->firstOrFail();

        $this->assertSame(BusinessType::SERVICE, $tenant->business_type);
        $this->assertSame(CommercialMode::CONSULTING_LEADS, $tenant->commercial_mode);
        $this->assertSame('consulting-core', $tenant->rubro_key);
    }

    public function test_registration_allows_missing_category_when_mode_is_selected(): void
    {
        [$city] = $this->referenceData();

        $this->postWithCsrf(route('tenant.register.store'), $this->validPayload([
            'business_name' => 'Consultoria Sin Rubro',
            'slug' => 'consultoria-sin-rubro',
            'email' => 'consultoria.sin.rubro@example.com',
            'city_id' => $city->id,
            'category_id' => null,
            'commercial_mode' => CommercialMode::CONSULTING_LEADS->value,
            'rubro_key' => 'consulting-core',
        ]))->assertRedirect('/app/consultoria-sin-rubro');

        $tenant = Tenant::query()->where('slug', 'consultoria-sin-rubro')->firstOrFail();

        $this->assertNull($tenant->category_id);
        $this->assertSame(CommercialMode::CONSULTING_LEADS, $tenant->commercial_mode);
    }

    public function test_hybrid_registration_is_rejected_without_pilot_gate(): void
    {
        [$city, $category] = $this->referenceData();

        $this->postWithCsrf(route('tenant.register.store'), $this->validPayload([
            'email' => 'hybrid.contract@example.com',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'commercial_mode' => CommercialMode::HYBRID->value,
        ]))->assertSessionHasErrors(['commercial_mode']);
    }

    /**
     * @return array{0: City, 1: Category}
     */
    private function referenceData(): array
    {
        $suffix = (string) str()->uuid();
        $cityId = DB::table('cities')->insertGetId([
            'name' => 'San Pedro Sula',
            'slug' => 'san-pedro-sula-'.$suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Servicios',
            'slug' => 'servicios-'.$suffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [City::query()->findOrFail($cityId), Category::query()->findOrFail($categoryId)];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        [$city, $category] = $this->referenceData();

        return array_merge([
            'business_name' => 'Negocio Contract',
            'slug' => 'negocio-contract',
            'name' => 'Owner Contract',
            'email' => 'owner.contract.success@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'commercial_mode' => CommercialMode::PRODUCTS->value,
            'whatsapp' => '+504 9999-0000',
            'selected_plan' => 'free',
            'terms_accepted' => true,
            'privacy_accepted' => true,
            'terms_version' => '2026-05-10',
            'privacy_version' => '2026-05-10',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWithCsrf(string $uri, array $payload)
    {
        return $this
            ->withSession(['_token' => 'test-token'])
            ->post($uri, array_merge(['_token' => 'test-token'], $payload));
    }

    private function seedGeneralSettings(): void
    {
        foreach ([
            'pro_plan_price' => 500.0,
            'grace_period_days' => 7,
            'free_plan_product_limit' => 10,
            'pro_plan_product_limit' => 100,
            'bank_transfer_instructions' => 'Transferencia bancaria',
            'support_whatsapp_number' => '+50499990000',
            'support_email' => 'soporte@example.com',
            'maintenance_mode' => false,
            'allow_registrations' => true,
        ] as $name => $payload) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'general', 'name' => $name],
                [
                    'locked' => false,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
