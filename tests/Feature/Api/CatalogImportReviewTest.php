<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Domain\Shared\Services\TenantContext;
use App\Enums\StockStatus;
use App\Infrastructure\Jobs\AI\ProcessCatalogImportItem;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CatalogImportReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_edits_ai_suggestion_without_creating_or_publishing_a_product(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->patchJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}", [
                'name' => 'Café Premium 12 oz',
                'description' => 'Café hondureño tostado artesanalmente.',
                'category_id' => null,
                'category_suggestion' => 'Bebidas',
                'price' => 125.50,
                'stock_quantity' => 4,
                'unit' => 'bolsa',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'needs_review')
            ->assertJsonPath('data.review.name', 'Café Premium 12 oz')
            ->assertJsonPath('data.review.price', 125.5);

        $item->refresh();
        self::assertSame(CatalogImportStatus::NeedsReview, $item->status);
        self::assertSame('Café Premium 12 oz', $item->review_payload['name']);
        self::assertSame(0, Product::withoutGlobalScopes()->count());
    }

    public function test_approval_rejects_a_review_that_still_has_pending_price_or_stock(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->patchJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}", [
                'name' => 'Café Premium 12 oz',
                'description' => 'Café hondureño tostado artesanalmente.',
                'price' => null,
                'stock_quantity' => null,
            ])
            ->assertOk();

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/approve");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'stock_quantity']);

        $item->refresh();
        self::assertSame(CatalogImportStatus::NeedsReview, $item->status);
        self::assertNull($item->product_id);
        self::assertSame(0, Product::withoutGlobalScopes()->count());
    }

    public function test_approval_creates_a_hidden_product_owned_by_the_active_tenant(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->withHeaders($headers)
            ->patchJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}", [
                'name' => 'Café Premium 12 oz',
                'description' => 'Café hondureño tostado artesanalmente.',
                'short_description' => 'Café premium artesanal.',
                'tags' => ['café', 'hondureño'],
                'colors' => ['café'],
                'material' => 'Empaque de papel',
                'dimensions' => [
                    'width' => 12.5,
                    'height' => null,
                    'depth' => null,
                    'unit' => 'cm',
                ],
                'variations' => [[
                    'name' => 'Tostado',
                    'options' => ['Medio', 'Oscuro'],
                ]],
                'condition' => 'new',
                'warnings' => ['Confirmar fecha de vencimiento'],
                'price' => 125.50,
                'stock_quantity' => 4,
                'unit' => 'bolsa',
            ])
            ->assertOk();

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $approvedProductId = $response->json('data.product_id');
        $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.product_id', $approvedProductId);

        $item->refresh();
        self::assertSame(CatalogImportStatus::Approved, $item->status);
        self::assertNotNull($item->product_id);

        $product = Product::withoutGlobalScopes()->findOrFail($item->product_id);
        self::assertSame((string) $tenant->id, (string) $product->tenant_id);
        self::assertSame('Café Premium 12 oz', $product->name);
        self::assertSame('125.50', $product->price);
        self::assertSame(4, $product->stock_quantity);
        self::assertSame(StockStatus::InStock, $product->stock_status);
        self::assertTrue($product->manage_stock);
        self::assertFalse($product->is_visible);
        self::assertNull($product->image_path);
        self::assertSame('Café premium artesanal.', $product->short_description);
        self::assertSame(['café', 'hondureño'], $product->tags);
        self::assertSame(['café'], $product->colors);
        self::assertSame('Empaque de papel', $product->material);
        self::assertSame(12.5, (float) $product->dimensions['width']);
        self::assertSame('new', $product->condition);
        self::assertSame(['Confirmar fecha de vencimiento'], $product->warnings);
        self::assertSame('bolsa', $product->unit);
        self::assertSame(1, Product::withoutGlobalScopes()->count());
    }

    public function test_approval_rechecks_plan_capacity_inside_the_transaction(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        $tenant->forceFill(['product_limit' => 1])->save();
        Product::factory()->forTenant($tenant)->create();
        $item->forceFill([
            'review_payload' => [
                'name' => 'Producto pendiente',
                'description' => null,
                'price' => 100.0,
                'stock_quantity' => 1,
            ],
        ])->save();
        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_limit']);

        $item->refresh();
        self::assertSame(CatalogImportStatus::NeedsReview, $item->status);
        self::assertNull($item->product_id);
        self::assertSame(1, Product::withoutGlobalScopes()->count());
    }

    public function test_batch_publication_promotes_only_an_approved_private_draft(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Storage::fake('public');
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->withHeaders($headers)
            ->patchJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}", [
                'name' => 'Café Premium 12 oz',
                'description' => 'Café hondureño tostado artesanalmente.',
                'price' => 125.50,
                'stock_quantity' => 4,
            ])
            ->assertOk();
        $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/approve")
            ->assertOk();

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/publish");

        $response->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_items', 1);

        $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_items', 1);

        $item->refresh();
        $batch->refresh();
        self::assertSame(CatalogImportStatus::Published, $item->status);
        self::assertSame(CatalogImportStatus::Published, $batch->status);
        self::assertNotNull($item->published_at);

        $product = Product::withoutGlobalScopes()->findOrFail($item->product_id);
        self::assertTrue($product->is_visible);
        self::assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('local')->assertMissing($item->source_path);
    }

    public function test_rejecting_a_reviewable_suggestion_removes_its_private_image_without_creating_a_product(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/reject");

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $item->refresh();
        self::assertSame(CatalogImportStatus::Rejected, $item->status);
        self::assertNull($item->product_id);
        self::assertSame(0, Product::withoutGlobalScopes()->count());
        Storage::disk('local')->assertMissing($item->source_path);
    }

    public function test_rejecting_an_approved_draft_is_idempotent_and_soft_deletes_it(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->withHeaders($headers)
            ->patchJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}", [
                'name' => 'Borrador rechazable',
                'price' => 10.00,
                'stock_quantity' => 1,
            ])
            ->assertOk();
        $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/approve")
            ->assertOk();
        $productId = $item->fresh()->product_id;

        $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
        $this->withHeaders($headers)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        self::assertNotNull(Product::withoutGlobalScopes()->withTrashed()->findOrFail($productId)->deleted_at);
    }

    public function test_retrying_a_failed_item_requeues_exactly_one_tenant_aware_job(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        $item->forceFill([
            'status' => CatalogImportStatus::Failed,
            'error_code' => 'provider_unavailable',
            'error_message' => 'Transient provider failure.',
        ])->save();
        Queue::fake();
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];
        $url = "/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/retry";

        $this->withHeaders($headers)->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');
        $this->withHeaders($headers)->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');

        $item->refresh();
        self::assertSame(CatalogImportStatus::Queued, $item->status);
        self::assertNull($item->error_code);
        self::assertNull($item->error_message);
        Queue::assertPushed(ProcessCatalogImportItem::class, 1);
        Queue::assertPushed(ProcessCatalogImportItem::class, static function (ProcessCatalogImportItem $job) use ($item, $tenant): bool {
            return $job->tenantId === (string) $tenant->id
                && $job->itemId === (string) $item->id;
        });
    }

    public function test_cancelling_a_batch_rejects_pending_items_and_removes_private_images(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->postJson("/api/v1/catalog-imports/{$batch->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $batch->refresh();
        $item->refresh();
        self::assertNotNull($batch->cancelled_at);
        self::assertSame(CatalogImportStatus::Rejected, $batch->status);
        self::assertSame(CatalogImportStatus::Rejected, $item->status);
        Storage::disk('local')->assertMissing($item->source_path);
    }

    public function test_show_returns_review_data_without_exposing_private_storage_or_provider_payload(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        $item->forceFill(['provider_response_ciphertext' => 'encrypted-secret'])->save();
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->getJson("/api/v1/catalog-imports/{$batch->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $batch->id)
            ->assertJsonPath('data.items.0.id', (string) $item->id)
            ->assertJsonPath('data.items.0.suggestion.name', 'Café')
            ->assertJsonMissingPath('data.items.0.source_path')
            ->assertJsonMissingPath('data.items.0.source_disk')
            ->assertJsonMissingPath('data.items.0.provider_response_ciphertext');
        self::assertStringNotContainsString('encrypted-secret', $response->getContent());
    }

    public function test_authenticated_owner_can_preview_the_private_source_without_learning_its_path(): void
    {
        [$owner, $tenant, $batch, $item] = $this->reviewableItem();
        Sanctum::actingAs($owner);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->get("/api/v1/catalog-imports/{$batch->id}/items/{$item->id}/source");

        $response->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('cache-control', 'no-store, private');
        self::assertSame('reviewable-image', $response->streamedContent());
        self::assertStringNotContainsString($item->source_path, (string) $response->headers->get('content-disposition'));
    }

    /** @return array{User, Tenant, CatalogImportBatch, CatalogImportItem} */
    private function reviewableItem(): array
    {
        Storage::fake('local');
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create();
        GlobalMarketFixture::assignCanada($tenant);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $tenant->memberships()->create([
            'user_id' => $owner->id,
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        $context = app(TenantContext::class);
        $context->reset();
        $context->setTenant((string) $tenant->id);
        app()->instance('currentTenant', $tenant);

        $batch = new CatalogImportBatch;
        $batch->forceFill([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $owner->id,
            'status' => CatalogImportStatus::NeedsReview,
            'total_items' => 1,
            'processed_items' => 1,
            'completed_at' => now(),
        ])->save();
        $path = "catalog-imports/{$tenant->id}/{$batch->id}/item.jpg";
        Storage::disk('local')->put($path, 'reviewable-image');

        $item = new CatalogImportItem;
        $item->forceFill([
            'tenant_id' => (string) $tenant->id,
            'batch_id' => (string) $batch->id,
            'status' => CatalogImportStatus::NeedsReview,
            'source_disk' => 'local',
            'source_path' => $path,
            'original_name' => 'item.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 16,
            'sha256' => hash('sha256', 'reviewable-image'),
            'normalized_payload' => [
                'name' => 'Café',
                'description' => 'Borrador IA',
                'category_suggestion' => 'Bebidas',
                'price' => null,
                'stock_quantity' => null,
                'unit' => null,
                'field_confidence' => [
                    'name' => 0.9,
                    'description' => 0.8,
                    'category_suggestion' => 0.7,
                    'price' => 0,
                    'stock_quantity' => 0,
                ],
                'overall_confidence' => 0.7,
                'review_reasons' => ['price_missing', 'stock_missing'],
            ],
            'field_confidence' => [
                'name' => 0.9,
                'description' => 0.8,
                'category_suggestion' => 0.7,
                'price' => 0,
                'stock_quantity' => 0,
            ],
            'overall_confidence' => 0.7,
            'processed_at' => now(),
        ])->save();

        $context->reset();
        app()->forgetInstance('currentTenant');

        return [$owner->refresh(), $tenant, $batch, $item];
    }
}
