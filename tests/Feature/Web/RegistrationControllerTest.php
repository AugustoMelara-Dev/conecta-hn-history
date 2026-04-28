<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Category;
use App\Models\City;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistrationControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function registration_page_exposes_business_type_and_segment_metadata_for_multi_rubro_onboarding(): void
    {
        Category::factory()->create(['name' => 'Restaurantes', 'slug' => 'restaurantes']);
        Category::factory()->create(['name' => 'Servicios', 'slug' => 'servicios']);
        City::factory()->create();

        $response = $this->get(route('tenant.register', [], false));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/RegisterTenant')
            ->has('businessTypes', 4)
            ->where('businessTypes.0.value', 'retail')
            ->where('businessTypes.1.value', 'food')
            ->where('businessTypes.2.value', 'service')
            ->where('businessTypes.3.value', 'hybrid')
            ->where('businessTypeSuggestions.restaurantes', 'food')
            ->where('businessTypeSuggestions.servicios', 'service')
            ->has('segmentProfiles')
            ->where('segmentProfiles.0.key', 'retail_general')
            ->where('segmentProfiles.0.label', 'Tienda y catalogo')
            ->where('segmentProfiles.1.key', 'restaurant_general')
            ->where('segmentProfiles.1.businessType', 'food')
        );
    }

    #[Test]
    public function registration_persists_selected_business_type_and_enables_matching_capabilities(): void
    {
        /** @var Category $category */
        $category = Category::factory()->create(['name' => 'Servicios', 'slug' => 'servicios']);
        /** @var City $city */
        $city = City::factory()->create();

        $response = $this->post(route('tenant.register.store', [], false), [
            'business_name' => 'Legal Norte',
            'slug' => 'legal-norte',
            'name' => 'Ana Abogada',
            'email' => 'ana@legalnorte.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'business_type' => 'service',
            'rubro_key' => 'service_general',
            'whatsapp' => '+50499990000',
            'selected_plan' => 'free',
            'terms_accepted' => true,
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();

        $tenant = Tenant::query()->where('slug', 'legal-norte')->first();

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertSame('service', $tenant->getRawOriginal('business_type'));
        $this->assertSame('service_general', $tenant->getRawOriginal('rubro_key'));
        $this->assertSame('service_general', $tenant->getRawOriginal('business_segment_key'));
        $this->assertSame('semilla', $tenant->getRawOriginal('plan_type'));
        $this->assertSame('inactive', $tenant->getRawOriginal('subscription_status'));
        $this->assertSame('provisional', $tenant->getRawOriginal('verification_status'));

        $this->assertDatabaseHas('tenant_business_claims', [
            'tenant_id' => $tenant->getKey(),
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('legal_consents', [
            'tenant_id' => $tenant->getKey(),
            'terms_version' => config('legal.current_versions.terms'),
            'privacy_version' => config('legal.current_versions.privacy'),
        ]);

        /** @var CapabilityManagerInterface $capabilityManager */
        $capabilityManager = $this->app->make(CapabilityManagerInterface::class);

        $this->assertTrue($capabilityManager->has($tenant, Capability::APPOINTMENT_BOOKING));
        $this->assertFalse($capabilityManager->has($tenant, Capability::KITCHEN_DISPLAY));
    }

    #[Test]
    public function pro_registration_opens_payment_boundary_before_panel_access(): void
    {
        /** @var Category $category */
        $category = Category::factory()->create(['name' => 'Servicios', 'slug' => 'servicios']);
        /** @var City $city */
        $city = City::factory()->create();

        $response = $this->post(route('tenant.register.store', [], false), [
            'business_name' => 'Consultoria Pro Norte',
            'slug' => 'consultoria-pro-norte',
            'name' => 'Carlos Consultor',
            'email' => 'carlos@consultoriapro.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'city_id' => $city->id,
            'category_id' => $category->id,
            'business_type' => 'service',
            'rubro_key' => 'service_general',
            'whatsapp' => '+50499990001',
            'selected_plan' => 'pro',
            'terms_accepted' => true,
        ]);

        $this->assertAuthenticated();

        $tenant = Tenant::query()->where('slug', 'consultoria-pro-norte')->firstOrFail();
        $payment = SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();

        $response->assertRedirect(route('payment.pending', ['payment' => $payment->id], false));

        $this->assertSame('pro', $tenant->getRawOriginal('plan_type'));
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $payment->status);
        $this->assertSame('bank_transfer', $payment->bank);
        $this->assertStringStartsWith('REG-', $payment->reference_number);
    }
}
