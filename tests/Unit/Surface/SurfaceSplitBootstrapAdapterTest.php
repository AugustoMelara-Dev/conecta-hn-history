<?php

declare(strict_types=1);

namespace Tests\Unit\Surface;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Surface\SurfaceSplitBootstrapAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class SurfaceSplitBootstrapAdapterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_gets_business_compatibility_redirects_for_legacy_workspace_paths(): void
    {
        config([
            'surfaces.origins.user_app' => 'https://www.vitobusinessos.com',
            'surfaces.origins.business_app' => 'https://app.vitobusinessos.com',
            'surfaces.origins.admin_app' => 'https://app.vitobusinessos.com',
            'surfaces.origins.platform_api' => 'https://api.vitobusinessos.com',
        ]);

        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'comedor-central',
        ]);
        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        $payload = app(SurfaceSplitBootstrapAdapter::class)->forUser($owner);

        self::assertSame('business_app', data_get($payload, 'context.home_surface'));
        self::assertSame('https://app.vitobusinessos.com', data_get($payload, 'origins.business_app'));
        self::assertSame(
            route('filament.app.resources.orders.index', ['tenant' => 'comedor-central'], false),
            $this->redirectForPath($payload, '/workspace/orders')
        );
        self::assertSame(
            route('filament.app.pages.manage-marketing', ['tenant' => 'comedor-central'], false),
            $this->redirectForPath($payload, '/workspace/marketing')
        );
        self::assertSame(
            route('filament.app.tenant.profile', ['tenant' => 'comedor-central'], false),
            $this->redirectForPath($payload, '/workspace/landing-page')
        );
    }

    public function test_customer_keeps_directory_redirects_on_legacy_paths(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER,
            'tenant_id' => null,
        ]);

        $payload = app(SurfaceSplitBootstrapAdapter::class)->forUser($customer);

        self::assertSame('user_app', data_get($payload, 'context.home_surface'));
        self::assertSame(route('directory.index', [], false), $this->redirectForPath($payload, '/dashboard'));
        self::assertSame(route('directory.index', [], false), $this->redirectForPath($payload, '/workspace/dashboard'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function redirectForPath(array $payload, string $path): ?string
    {
        $match = collect((array) data_get($payload, 'compatibility.legacy_paths', []))
            ->firstWhere('path', $path);

        return is_array($match) ? (string) data_get($match, 'redirect_to') : null;
    }
}
