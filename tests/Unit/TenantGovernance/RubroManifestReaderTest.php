<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RubroManifestReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_tenant_resolves_service_manifest_and_capabilities(): void
    {
        $tenant = Tenant::factory()->create([
            'business_type' => 'service',
            'business_segment_key' => null,
        ]);

        $reader = app(RubroManifestReaderInterface::class);
        $manifest = $reader->forTenant($tenant);
        $capabilities = $reader->capabilitiesForTenant($tenant);

        self::assertSame('service_general', $manifest['rubro_key']);
        self::assertSame('rubro-manifest-v1', $manifest['manifest_version']);
        self::assertContains(Capability::AI_CONTENT->value, $capabilities);
        self::assertContains(Capability::APPOINTMENT_BOOKING->value, $capabilities);
        self::assertContains('booking', $manifest['modules']['user_app']);
    }

    public function test_unknown_segment_falls_back_to_business_type_default(): void
    {
        $tenant = Tenant::factory()->create([
            'business_type' => 'food',
            'business_segment_key' => 'unknown_segment',
        ]);

        $manifest = app(RubroManifestReaderInterface::class)->forTenant($tenant);

        self::assertSame('restaurant_general', $manifest['rubro_key']);
        self::assertSame('Restaurante y comida', $manifest['presentation']['label']);
    }

    public function test_rubro_key_has_priority_over_legacy_segment_and_business_type(): void
    {
        $tenant = Tenant::factory()->create([
            'business_type' => 'retail',
            'business_segment_key' => 'retail_general',
            'rubro_key' => 'service_general',
        ]);

        $manifest = app(RubroManifestReaderInterface::class)->forTenant($tenant);

        self::assertSame('service_general', $manifest['rubro_key']);
        self::assertSame('Servicios y citas', $manifest['presentation']['label']);
    }

    public function test_manifest_exposes_registration_onboarding_dynamic_forms_and_feature_map(): void
    {
        $tenant = Tenant::factory()->create([
            'business_type' => 'service',
        ]);

        $reader = app(RubroManifestReaderInterface::class);

        self::assertCount(4, $reader->registrationOptions());
        self::assertSame('service', $reader->categorySuggestions()['servicios']);
        self::assertSame('Activa tus servicios reservables', $reader->onboardingForTenant($tenant)['title']);
        self::assertSame('service_intake', $reader->dynamicFormsForTenant($tenant)['entities'][0]['entity_key']);
        self::assertContains(Capability::APPOINTMENT_BOOKING->value, $reader->featureCapabilityMap()['service']['capabilities']);
    }

    public function test_manifest_is_source_for_business_app_crm_labels_and_dynamic_entity_scope(): void
    {
        $tenant = Tenant::factory()->create([
            'business_type' => 'service',
            'rubro_key' => 'service_general',
            'business_segment_key' => 'legacy_service',
        ]);

        $reader = app(RubroManifestReaderInterface::class);

        self::assertSame('Servicios y citas', $reader->labelForRubroKey('service_general'));
        self::assertSame('Legacy Service', $reader->labelForRubroKey('legacy_service'));
        self::assertSame('Tu centro operativo para servicios y agenda', $reader->businessAppForTenant($tenant)['dashboard_title']);
        self::assertContains('service_rebook', $reader->crmForTenant($tenant)['templates']);
        self::assertSame(['service_intake', 'client_discovery'], $reader->allowedDynamicEntityKeysForTenant($tenant));
        self::assertSame('CRM y continuidad de servicios', $reader->growthProfileForTenant($tenant)['headline']);
        self::assertCount(2, $reader->growthProfileForTenant($tenant)['journeys']);
    }

    public function test_legacy_segment_and_storefront_configs_are_removed(): void
    {
        foreach ([
            'business-segments.php',
            'storefront-playbooks.php',
            'storefront-segments.php',
            'storefront-verticals.php',
        ] as $configFile) {
            self::assertFileDoesNotExist(config_path($configFile));
        }
    }
}
