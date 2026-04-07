<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Tenant\Commands\UpdateTenantPanelSettingsCommand;
use App\Application\Tenant\Commands\UpdateTenantPublicProfileCommand;
use App\Application\Tenant\Handlers\UpdateTenantPanelSettingsHandler;
use App\Application\Tenant\Handlers\UpdateTenantPublicProfileHandler;
use App\Models\OpeningHour;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class TenantWriteIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_panel_settings_handler_only_updates_the_target_tenant(): void
    {
        $tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'whatsapp_number' => '1111-1111',
        ]);
        $tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'whatsapp_number' => '2222-2222',
        ]);

        $this->app->make(UpdateTenantPanelSettingsHandler::class)->handle(
            new UpdateTenantPanelSettingsCommand(
                tenantId: (string) $tenantA->getKey(),
                name: 'Tenant A Renovado',
                whatsappNumber: '9999-0000',
                description: 'Descripcion aislada',
                address: 'Palmira',
                whatsappMessage: 'Hola desde A',
            )
        );

        $tenantA->refresh();
        $tenantB->refresh();

        self::assertSame('Tenant A Renovado', $tenantA->name);
        self::assertSame('9999-0000', $tenantA->whatsapp_number);
        self::assertSame('Tenant B', $tenantB->name);
        self::assertSame('2222-2222', $tenantB->whatsapp_number);
    }

    public function test_public_profile_handler_ignores_pro_only_fields_for_free_tenant_and_keeps_other_tenants_untouched(): void
    {
        $freeTenant = Tenant::factory()->create([
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'brand_color' => '#2563EB',
            'simple_schedule' => 'Lunes a Viernes 8:00 AM - 5:00 PM',
        ]);
        $otherTenant = Tenant::factory()->pro()->create([
            'brand_color' => '#111111',
        ]);

        $foreignOpeningHour = new OpeningHour;
        $foreignOpeningHour->forceFill([
            'tenant_id' => $otherTenant->getKey(),
            'day_of_week' => 1,
            'opening_time' => '10:00:00',
            'closing_time' => '19:00:00',
        ]);
        $foreignOpeningHour->save();

        $staleFreeOpeningHour = new OpeningHour;
        $staleFreeOpeningHour->forceFill([
            'tenant_id' => $freeTenant->getKey(),
            'day_of_week' => 2,
            'opening_time' => '09:00:00',
            'closing_time' => '18:00:00',
        ]);
        $staleFreeOpeningHour->save();

        $this->app->make(UpdateTenantPublicProfileHandler::class)->handle(
            new UpdateTenantPublicProfileCommand(
                tenantId: (string) $freeTenant->getKey(),
                profileData: [
                    'name' => 'Tenant FREE',
                    'simple_schedule' => 'Sabados de 9:00 AM a 1:00 PM',
                    'brand_color' => '#000000',
                    'website_url' => 'https://intruso.test',
                ],
                hoursData: [
                    [
                        'day' => 'lunes',
                        'open' => '08:00',
                        'close' => '17:00',
                        'is_closed' => false,
                    ],
                ],
            )
        );

        $freeTenant->refresh();
        $otherTenant->refresh();

        self::assertSame('Tenant FREE', $freeTenant->name);
        self::assertSame('Sabados de 9:00 AM a 1:00 PM', $freeTenant->simple_schedule);
        self::assertSame('#2563EB', $freeTenant->brand_color);
        self::assertNull($freeTenant->website_url);
        self::assertSame(0, OpeningHour::query()->withoutGlobalScopes()->where('tenant_id', $freeTenant->getKey())->count());
        self::assertSame('#111111', $otherTenant->brand_color);
        self::assertSame(1, OpeningHour::query()->withoutGlobalScopes()->where('tenant_id', $otherTenant->getKey())->count());
    }
}
