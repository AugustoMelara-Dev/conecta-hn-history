<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Domain\Shared\Services\TenantContext;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CatalogImportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_tenant_cannot_access_or_mutate_any_import_operation(): void
    {
        [$actor, $actorTenant] = $this->ownerAndTenant('catalog-actor');
        [, $victimTenant] = $this->ownerAndTenant('catalog-victim');
        [$batch, $item] = $this->reviewableItem($victimTenant);
        Sanctum::actingAs($actor);
        $headers = ['X-Tenant-ID' => (string) $actorTenant->id];

        $this->withHeaders($headers)->getJson("/api/v1/catalog-imports/{$batch->id}")->assertNotFound();
        $this->withHeaders($headers)->get("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/source")->assertNotFound();
        $this->withHeaders($headers)->patchJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}", [
            'name' => 'Ataque',
        ])->assertNotFound();
        $this->withHeaders($headers)->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/approve")->assertNotFound();
        $this->withHeaders($headers)->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/reject")->assertNotFound();
        $this->withHeaders($headers)->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/retry")->assertNotFound();
        $this->withHeaders($headers)->postJson("/api/v1/catalog-imports/{$batch->id}/publish")->assertNotFound();
        $this->withHeaders($headers)->postJson("/api/v1/catalog-imports/{$batch->id}/cancel")->assertNotFound();

        $batch->refresh();
        $item->refresh();
        self::assertSame(CatalogImportStatus::NeedsReview, $batch->status);
        self::assertSame(CatalogImportStatus::NeedsReview, $item->status);
        self::assertNull($item->review_payload);
        self::assertNull($item->product_id);
        Storage::disk('local')->assertExists($item->source_path);
    }

    public function test_viewer_membership_cannot_edit_a_catalog_import(): void
    {
        [, $tenant] = $this->ownerAndTenant('catalog-viewer');
        [$batch, $item] = $this->reviewableItem($tenant);
        $viewer = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->memberships()->create([
            'user_id' => $viewer->id,
            'role' => Role::VIEWER,
            'accepted_at' => now(),
        ]);
        Sanctum::actingAs($viewer);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->patchJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}", [
                'name' => 'Cambio no autorizado',
            ])
            ->assertForbidden();

        $item->refresh();
        self::assertNull($item->review_payload);
    }

    /** @return array{User, Tenant} */
    private function ownerAndTenant(string $slug): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create(['slug' => $slug]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner->refresh(), $tenant];
    }

    /** @return array{CatalogImportBatch, CatalogImportItem} */
    private function reviewableItem(Tenant $tenant): array
    {
        Storage::fake('local');
        $context = app(TenantContext::class);
        $context->reset();
        $context->setTenant((string) $tenant->id);
        app()->instance('currentTenant', $tenant);

        $batch = new CatalogImportBatch;
        $batch->forceFill([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $tenant->user_id,
            'status' => CatalogImportStatus::NeedsReview,
            'total_items' => 1,
            'processed_items' => 1,
        ])->save();
        $path = "catalog-imports/{$tenant->id}/{$batch->id}/item.jpg";
        Storage::disk('local')->put($path, 'private-victim-image');
        $item = new CatalogImportItem;
        $item->forceFill([
            'tenant_id' => (string) $tenant->id,
            'batch_id' => (string) $batch->id,
            'status' => CatalogImportStatus::NeedsReview,
            'source_disk' => 'local',
            'source_path' => $path,
            'original_name' => 'item.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 20,
            'sha256' => hash('sha256', 'private-victim-image'),
            'normalized_payload' => [
                'name' => 'Producto privado',
                'description' => 'Solo para revisión.',
                'category_suggestion' => null,
                'price' => null,
                'stock_quantity' => null,
                'unit' => null,
                'field_confidence' => [],
                'overall_confidence' => 0.5,
                'review_reasons' => ['review'],
            ],
        ])->save();

        $context->reset();
        app()->forgetInstance('currentTenant');

        return [$batch, $item];
    }
}
