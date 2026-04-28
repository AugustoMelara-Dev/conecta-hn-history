<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\App\Resources\MenuSectionResource;
use App\Models\MenuSection;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class MenuSectionResourceManifestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menu_sections_follow_rubro_manifest_modules(): void
    {
        Gate::shouldReceive('allows')
            ->with('viewAny', MenuSection::class)
            ->andReturnTrue()
            ->times(3);

        foreach (['retail', 'food', 'hybrid'] as $businessType) {
            Filament::setTenant(Tenant::factory()->create(['business_type' => $businessType]), isQuiet: true);

            self::assertTrue(MenuSectionResource::canViewAny(), "{$businessType} should expose menu sections.");
        }
    }

    public function test_service_tenants_do_not_expose_menu_sections(): void
    {
        Gate::shouldReceive('allows')
            ->with('viewAny', MenuSection::class)
            ->andReturnTrue()
            ->never();

        Filament::setTenant(Tenant::factory()->create(['business_type' => 'service']), isQuiet: true);

        self::assertFalse(MenuSectionResource::canViewAny());
    }
}
