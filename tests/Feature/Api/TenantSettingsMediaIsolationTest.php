<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Infrastructure\Storage\LaravelMediaStorageService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TenantSettingsMediaIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_updates_settings_and_stores_logo_under_uuid_tenant_directory(): void
    {
        [$owner, $tenant] = $this->tenantOwner('settings-media');
        Storage::fake('public');
        Storage::disk('public')->put('tenants/'.$tenant->id.'/logos/old.png', 'old');
        $tenant->forceFill(['logo_path' => 'tenants/'.$tenant->id.'/logos/old.png'])->save();
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->post('/api/v1/settings/update', [
                'whatsapp_number' => '+504 9999-9999',
                'bank_name' => 'Banco Seguro',
                'logo' => UploadedFile::fake()->image('brand.png', 200, 200),
            ]);

        $response->assertOk()->assertJsonPath('data.id', (string) $tenant->id);
        $tenant->refresh();
        $this->assertSame('+504 9999-9999', $tenant->whatsapp_number);
        $this->assertSame('Banco Seguro', $tenant->bank_name);
        $this->assertIsString($tenant->logo_path);
        $this->assertStringStartsWith('tenants/'.$tenant->id.'/logos/', $tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);
        Storage::disk('public')->assertMissing('tenants/'.$tenant->id.'/logos/old.png');
    }

    public function test_unrelated_tenant_header_cannot_write_settings_or_media(): void
    {
        [$owner] = $this->tenantOwner('settings-a');
        [, $otherTenant] = $this->tenantOwner('settings-b', 'settings-other@example.test');
        Storage::fake('public');
        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->post('/api/v1/settings/update', [
                'bank_name' => 'Ataque',
                'logo' => UploadedFile::fake()->image('hostile.png'),
            ])
            ->assertForbidden();

        $this->assertNotSame('Ataque', $otherTenant->fresh()->bank_name);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_viewer_membership_cannot_update_tenant_settings(): void
    {
        [, $tenant] = $this->tenantOwner('settings-viewer-owner');
        $viewer = User::factory()->create(['email' => 'settings-viewer@example.test']);
        $tenant->memberships()->create([
            'user_id' => $viewer->id,
            'role' => Role::VIEWER,
            'accepted_at' => now(),
        ]);
        Sanctum::actingAs($viewer);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson('/api/v1/settings/update', ['bank_name' => 'Sin permiso'])
            ->assertForbidden();

        $this->assertNotSame('Sin permiso', $tenant->fresh()->bank_name);
    }

    public function test_media_adapter_refuses_cross_tenant_or_traversal_deletion(): void
    {
        [, $tenant] = $this->tenantOwner('settings-delete-a');
        [, $otherTenant] = $this->tenantOwner('settings-delete-b', 'settings-delete-other@example.test');
        Storage::fake('public');
        $otherPath = 'tenants/'.$otherTenant->id.'/logos/private.png';
        Storage::disk('public')->put($otherPath, 'private');
        $storage = app(LaravelMediaStorageService::class);

        foreach ([
            $otherPath,
            'tenants/'.$tenant->id.'/logos/../private.png',
        ] as $hostilePath) {
            try {
                $storage->delete((string) $tenant->id, $hostilePath);
                self::fail('Cross-tenant and traversal paths must be rejected.');
            } catch (InvalidArgumentException) {
                // Expected fail-closed behavior.
            }
        }

        Storage::disk('public')->assertExists($otherPath);
    }

    /** @return array{User, Tenant} */
    private function tenantOwner(string $slug, string $email = 'settings-owner@example.test'): array
    {
        $owner = User::factory()->tenantOwner()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->create(['slug' => $slug]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner->refresh(), $tenant];
    }
}
