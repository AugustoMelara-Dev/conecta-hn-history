<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\DatabaseSchemaInspector;
use Tests\TestCase;

final class TenantRelationshipConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_databases_expose_tenant_aware_composite_foreign_keys(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite cannot add foreign keys to existing tables; PostgreSQL and MySQL are the production gates.');
        }

        self::assertTrue(DatabaseSchemaInspector::hasForeignKey(
            'catalog_import_items',
            ['tenant_id', 'batch_id'],
            'catalog_import_batches',
            ['tenant_id', 'id'],
        ));
        self::assertTrue(DatabaseSchemaInspector::hasForeignKey(
            'appointments',
            ['tenant_id', 'service_id'],
            'services',
            ['tenant_id', 'id'],
        ));
        self::assertTrue(DatabaseSchemaInspector::hasForeignKey(
            'orders',
            ['tenant_id', 'customer_profile_id'],
            'customer_profiles',
            ['tenant_id', 'id'],
        ));
        self::assertTrue(DatabaseSchemaInspector::hasForeignKey(
            'loyalty_ledger',
            ['tenant_id', 'order_id', 'user_id'],
            'orders',
            ['tenant_id', 'id', 'user_id'],
        ));
    }

    public function test_catalog_item_cannot_reference_another_tenants_batch(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenantA, $tenantB] = $this->tenants();
        $batchA = (string) Str::uuid();
        $batchB = (string) Str::uuid();
        $item = (string) Str::uuid();

        DB::table('catalog_import_batches')->insert([
            ['id' => $batchA, 'tenant_id' => $tenantA->getKey(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => $batchB, 'tenant_id' => $tenantB->getKey(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('catalog_import_items')->insert([
            'id' => $item,
            'batch_id' => $batchA,
            'tenant_id' => $tenantA->getKey(),
            'source_path' => 'tenant-a/product.jpg',
            'original_name' => 'product.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'sha256' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertConstraintRejects(
            static fn () => DB::table('catalog_import_items')->where('id', $item)->update(['batch_id' => $batchB]),
        );
    }

    public function test_appointment_cannot_reference_another_tenants_service(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenantA, $tenantB] = $this->tenants();
        $serviceA = Service::factory()->create(['tenant_id' => $tenantA->getKey()]);
        $providerA = ServiceProvider::factory()->create(['tenant_id' => $tenantA->getKey()]);
        $serviceB = Service::factory()->create(['tenant_id' => $tenantB->getKey()]);
        $appointmentId = DB::table('appointments')->insertGetId([
            'tenant_id' => $tenantA->getKey(),
            'service_id' => $serviceA->getKey(),
            'service_provider_id' => $providerA->getKey(),
            'scheduled_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertConstraintRejects(
            static fn () => DB::table('appointments')->where('id', $appointmentId)->update([
                'service_id' => $serviceB->getKey(),
            ]),
        );
    }

    public function test_order_cannot_reference_another_tenants_customer_profile(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenantA, $tenantB] = $this->tenants();
        $order = Order::factory()->create(['tenant_id' => $tenantA->getKey()]);
        $profileId = DB::table('customer_profiles')->insertGetId([
            'tenant_id' => $tenantB->getKey(),
            'name' => 'Cliente B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertConstraintRejects(
            static fn () => DB::table('orders')->where('id', $order->getKey())->update([
                'customer_profile_id' => $profileId,
            ]),
        );
    }

    public function test_loyalty_entry_must_match_the_orders_tenant_and_user(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenantA, $tenantB] = $this->tenants();
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'tenant_id' => $tenantA->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->assertConstraintRejects(static fn () => DB::table('loyalty_ledger')->insert([
            'tenant_id' => $tenantB->getKey(),
            'user_id' => $user->getKey(),
            'order_id' => $order->getKey(),
            'idempotency_key' => "order:{$order->getKey()}:earn",
            'type' => 'earn',
            'amount' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_product_delete_preserves_catalog_item_and_nulls_its_reference(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenant] = $this->tenants();
        $product = Product::factory()->create(['tenant_id' => $tenant->getKey()]);
        [$itemId] = $this->catalogItems($tenant, productId: (int) $product->getKey());

        DB::table('products')->where('id', $product->getKey())->delete();

        self::assertNull(DB::table('catalog_import_items')->where('id', $itemId)->value('product_id'));
    }

    public function test_duplicate_source_delete_preserves_catalog_item_and_nulls_its_reference(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenant] = $this->tenants();
        [$sourceId, $duplicateId] = $this->catalogItems($tenant, withDuplicate: true);

        DB::table('catalog_import_items')->where('id', $sourceId)->delete();

        self::assertNull(DB::table('catalog_import_items')->where('id', $duplicateId)->value('duplicate_of_id'));
    }

    public function test_customer_profile_delete_preserves_order_and_nulls_its_reference(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenant] = $this->tenants();
        $profileId = DB::table('customer_profiles')->insertGetId([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cliente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order = Order::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'customer_profile_id' => $profileId,
        ]);

        DB::table('customer_profiles')->where('id', $profileId)->delete();

        self::assertNull(DB::table('orders')->where('id', $order->getKey())->value('customer_profile_id'));
    }

    public function test_order_delete_preserves_loyalty_entry_and_nulls_its_reference(): void
    {
        $this->requireProductionConstraintDriver();
        [$tenant] = $this->tenants();
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $ledgerId = DB::table('loyalty_ledger')->insertGetId([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'order_id' => $order->getKey(),
            'idempotency_key' => "order:{$order->getKey()}:earn",
            'type' => 'earn',
            'amount' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->where('id', $order->getKey())->delete();

        self::assertNull(DB::table('loyalty_ledger')->where('id', $ledgerId)->value('order_id'));
    }

    /** @return array{Tenant, Tenant} */
    private function tenants(): array
    {
        app(TenantContext::class)->setAsSystemRoot();

        return [Tenant::factory()->create(), Tenant::factory()->create()];
    }

    /** @return array{string, string|null} */
    private function catalogItems(Tenant $tenant, ?int $productId = null, bool $withDuplicate = false): array
    {
        $batchId = (string) Str::uuid();
        $sourceId = (string) Str::uuid();
        $duplicateId = $withDuplicate ? (string) Str::uuid() : null;
        DB::table('catalog_import_batches')->insert([
            'id' => $batchId,
            'tenant_id' => $tenant->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('catalog_import_items')->insert($this->catalogItemRow(
            $sourceId,
            $batchId,
            $tenant,
            productId: $productId,
        ));

        if ($duplicateId !== null) {
            DB::table('catalog_import_items')->insert($this->catalogItemRow(
                $duplicateId,
                $batchId,
                $tenant,
                duplicateOfId: $sourceId,
            ));
        }

        return [$sourceId, $duplicateId];
    }

    /** @return array<string, mixed> */
    private function catalogItemRow(
        string $id,
        string $batchId,
        Tenant $tenant,
        ?int $productId = null,
        ?string $duplicateOfId = null,
    ): array {
        return [
            'id' => $id,
            'batch_id' => $batchId,
            'tenant_id' => $tenant->getKey(),
            'duplicate_of_id' => $duplicateOfId,
            'product_id' => $productId,
            'source_path' => "{$id}.jpg",
            'original_name' => 'product.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'sha256' => hash('sha256', $id),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function assertConstraintRejects(callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException) {
            return;
        }

        self::fail('The database accepted a cross-tenant relationship.');
    }

    private function requireProductionConstraintDriver(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite cannot add foreign keys to existing tables; PostgreSQL and MySQL are the production gates.');
        }

        self::assertContains(DB::getDriverName(), ['pgsql', 'mysql']);
        self::assertTrue(Schema::hasTable('tenants'));
    }
}
