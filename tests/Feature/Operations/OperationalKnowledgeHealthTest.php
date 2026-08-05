<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Operations\OperationalKnowledgeHealth;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class OperationalKnowledgeHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_reports_actionable_catalog_import_and_knowledge_issues_per_tenant(): void
    {
        [$tenant, $owner] = $this->tenant('health@example.test');
        [$other] = $this->tenant('health-other@example.test');

        $product = Product::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Unready product',
            'slug' => 'unready-product',
            'price' => 0,
            'price_minor' => 0,
            'currency' => 'CAD',
            'manage_stock' => true,
            'stock_quantity' => 0,
            'is_visible' => false,
        ]);
        Product::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $other->id,
            'name' => 'Other tenant product',
            'slug' => 'other-tenant-product',
            'price' => 0,
            'price_minor' => 0,
            'currency' => 'CAD',
            'manage_stock' => true,
            'stock_quantity' => 0,
            'is_visible' => false,
        ]);
        $service = Service::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Unready service',
            'description' => null,
            'duration_minutes' => 0,
            'price' => 0,
            'price_minor' => 0,
            'currency' => 'CAD',
            'is_active' => true,
        ]);
        DB::table('ai_knowledge_chunks')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'resource_type' => 'product',
            'resource_id' => (string) $product->id,
            'visibility' => 'public',
            'content' => 'Old product facts',
            'content_hash' => hash('sha256', 'Old product facts'),
            'embedding' => json_encode([0.1], JSON_THROW_ON_ERROR),
            'embedding_model' => 'test',
            'metadata' => null,
            'indexed_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $product->forceFill(['updated_at' => now()])->saveQuietly();

        $batch = CatalogImportBatch::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'created_by' => $owner->id,
            'status' => 'needs_review',
            'kind' => 'product',
        ]);
        CatalogImportItem::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'batch_id' => $batch->id,
            'status' => 'rejected',
            'aggregate_type' => 'product',
            'row_number' => 2,
            'source_disk' => 'local',
            'source_path' => 'imports/rejected.csv',
            'original_name' => 'rejected.csv#2',
            'mime_type' => 'text/csv',
            'size_bytes' => 1,
            'sha256' => hash('sha256', 'rejected'),
        ]);

        $issues = app(OperationalKnowledgeHealth::class)->forTenant((string) $tenant->id);
        $codes = array_map(static fn ($issue): string => $issue->code, $issues);

        self::assertContains('product_unpublished', $codes);
        self::assertContains('product_price_missing', $codes);
        self::assertContains('product_inventory_unavailable', $codes);
        self::assertContains('knowledge_index_stale', $codes);
        self::assertContains('service_price_missing', $codes);
        self::assertContains('service_duration_missing', $codes);
        self::assertContains('service_location_missing', $codes);
        self::assertContains('service_schedule_missing', $codes);
        self::assertContains('import_rejected', $codes);
        self::assertNotContains('Other tenant product', array_map(
            static fn ($issue): string => $issue->evidenceLabel,
            $issues,
        ));
        foreach ($issues as $issue) {
            self::assertNotSame('', $issue->severity);
            self::assertNotSame('', $issue->evidenceId);
            self::assertStringStartsWith('/', $issue->remediationUrl);
            self::assertNotNull($issue->checkedAt);
        }

        self::assertNotNull($service->id);
    }

    /** @return array{Tenant, User} */
    private function tenant(string $email): array
    {
        $owner = User::factory()->tenantOwner()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->pro()->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
        ]);
        GlobalMarketFixture::assignCanada($tenant);

        return [$tenant, $owner];
    }
}
