<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Infrastructure\Jobs\AI\ProcessCatalogImportItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CatalogImportUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_uploads_multiple_private_images_and_queues_one_tenant_job_per_item(): void
    {
        [$owner, $tenant] = $this->tenantOwner('catalog-import');
        Storage::fake('local');
        Queue::fake();
        Sanctum::actingAs($owner);

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/catalog-imports', [
                'images' => [
                    UploadedFile::fake()->image('cafe.jpg', 800, 800),
                    UploadedFile::fake()->image('pan.png', 800, 800),
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonCount(2, 'data.items');

        $batchId = (string) $response->json('data.id');
        $items = $response->json('data.items');

        self::assertSame(2, DB::table('catalog_import_items')->where('tenant_id', $tenant->id)->count());
        foreach ($items as $item) {
            self::assertSame('queued', $item['status']);
            self::assertArrayNotHasKey('source_path', $item);
            $sourcePath = DB::table('catalog_import_items')->where('id', $item['id'])->value('source_path');
            self::assertIsString($sourcePath);
            self::assertStringStartsWith("catalog-imports/{$tenant->id}/{$batchId}/", $sourcePath);
            Storage::disk('local')->assertExists($sourcePath);
        }

        Queue::assertPushed(ProcessCatalogImportItem::class, 2);
        Queue::assertPushed(ProcessCatalogImportItem::class, function (ProcessCatalogImportItem $job) use ($tenant): bool {
            return $job->tenantId === (string) $tenant->id;
        });
    }

    public function test_duplicate_image_creates_an_auditable_rejected_item_without_duplicate_ai_job(): void
    {
        [$owner, $tenant] = $this->tenantOwner('catalog-dedup');
        Storage::fake('local');
        Queue::fake();
        Sanctum::actingAs($owner);
        $imageBytes = UploadedFile::fake()->image('same.jpg', 600, 600)->getContent();

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->post('/api/v1/catalog-imports', [
                'images' => [
                    UploadedFile::fake()->createWithContent('first.jpg', $imageBytes),
                    UploadedFile::fake()->createWithContent('duplicate.jpg', $imageBytes),
                ],
            ]);

        $response->assertCreated();
        $items = DB::table('catalog_import_items')
            ->where('tenant_id', $tenant->id)
            ->get();

        self::assertCount(2, $items);
        $original = $items->first(static fn (object $item): bool => $item->duplicate_of_id === null);
        $duplicate = $items->first(static fn (object $item): bool => $item->duplicate_of_id !== null);
        self::assertNotNull($original);
        self::assertNotNull($duplicate);
        self::assertSame('queued', $original->status);
        self::assertSame('rejected', $duplicate->status);
        self::assertSame($original->id, $duplicate->duplicate_of_id);
        self::assertSame('duplicate_image', $duplicate->error_code);
        Queue::assertPushed(ProcessCatalogImportItem::class, 1);
    }

    public function test_upload_respects_the_tenants_configured_product_capacity(): void
    {
        [$owner, $tenant] = $this->tenantOwner('catalog-capacity');
        $tenant->forceFill(['product_limit' => 1])->save();
        Storage::fake('local');
        Queue::fake();
        Sanctum::actingAs($owner);

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/catalog-imports', [
                'images' => [
                    UploadedFile::fake()->image('one.jpg', 600, 600),
                    UploadedFile::fake()->image('two.jpg', 600, 600),
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['images']);
        self::assertSame(0, DB::table('catalog_import_batches')->count());
        self::assertSame(0, DB::table('catalog_import_items')->count());
        Queue::assertNothingPushed();
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_rejects_spoofed_image_extensions_using_detected_mime(): void
    {
        [$owner, $tenant] = $this->tenantOwner('catalog-mime');
        Storage::fake('local');
        Queue::fake();
        Sanctum::actingAs($owner);

        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $tenant->id,
            'Accept' => 'application/json',
        ])->post('/api/v1/catalog-imports', [
            'images' => [
                UploadedFile::fake()->createWithContent(
                    'hostile.jpg',
                    '<svg onload="alert(1)"><script>alert(2)</script></svg>',
                ),
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0']);
        self::assertSame(0, DB::table('catalog_import_items')->count());
        Queue::assertNothingPushed();
        self::assertSame([], Storage::disk('local')->allFiles());
    }

    /** @return array{User, Tenant} */
    private function tenantOwner(string $slug): array
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
}
