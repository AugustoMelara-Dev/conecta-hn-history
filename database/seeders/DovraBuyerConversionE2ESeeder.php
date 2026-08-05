<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Markets\HondurasMarketBackfill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Deterministic synthetic fixture for the public buyer conversion journey.
 *
 * This seeder is intentionally restricted to disposable local/testing data.
 * It creates one published Pro tenant and one finite-stock product, but no
 * payment, billing, subscription, provider, or customer transaction rows.
 */
final class DovraBuyerConversionE2ESeeder extends Seeder
{
    private const TENANT_SLUG = 'dovra-buyer-conversion-e2e';

    private const OWNER_EMAIL = 'dovra.buyer.conversion.e2e@example.test';

    private const PRODUCT_SLUG = 'producto-buyer-e2e';

    private const MARKET_COUNTRY = 'HN';

    private const MARKET_LOCALE = 'es-HN';

    private const MARKET_CURRENCY = 'HNL';

    private const MARKET_TIMEZONE = 'America/Tegucigalpa';

    public function run(HondurasMarketBackfill $backfill): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException(
                'DovraBuyerConversionE2ESeeder is restricted to local or testing environments.',
            );
        }

        if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_BUYER_CONVERSION') !== 'true') {
            throw new \LogicException(
                'DovraBuyerConversionE2ESeeder requires DOVRA_E2E_ALLOW_SYNTHETIC_BUYER_CONVERSION=true.',
            );
        }

        DB::transaction(function () use ($backfill): void {
            $now = now();
            $categoryId = $this->category($now);
            $cityId = $this->city($now);
            $ownerId = $this->owner($now);
            $tenantId = $this->tenant($ownerId, $categoryId, $cityId, $now);

            DB::table('users')
                ->where('id', $ownerId)
                ->update($this->onlyExistingColumns('users', [
                    'tenant_id' => $tenantId,
                    'updated_at' => $now,
                ]));

            $backfill->run();
            $this->product($tenantId, $categoryId, $cityId, $now);
        });
    }

    private function category(mixed $now): ?int
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        DB::table('categories')->updateOrInsert(
            ['slug' => 'restaurantes'],
            $this->onlyExistingColumns('categories', [
                'name' => 'Restaurantes',
                'icon' => 'utensils',
                'color' => '#0ea5e9',
                'is_active' => true,
                'is_featured' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return (int) DB::table('categories')->where('slug', 'restaurantes')->value('id');
    }

    private function city(mixed $now): ?int
    {
        if (! Schema::hasTable('cities')) {
            return null;
        }

        DB::table('cities')->updateOrInsert(
            ['slug' => 'tegucigalpa'],
            $this->onlyExistingColumns('cities', [
                'name' => 'Tegucigalpa',
                'municipality_id' => null,
                'is_active' => true,
                'is_featured' => true,
                'coordinates' => json_encode([
                    'lat' => 14.0723,
                    'lng' => -87.1921,
                ], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return (int) DB::table('cities')->where('slug', 'tegucigalpa')->value('id');
    }

    private function owner(mixed $now): string
    {
        $existing = DB::table('users')->where('email', self::OWNER_EMAIL)->first();
        $ownerId = (string) ($existing->id ?? Str::uuid());

        DB::table('users')->updateOrInsert(
            ['email' => self::OWNER_EMAIL],
            $this->onlyExistingColumns('users', [
                'id' => $ownerId,
                'name' => 'Buyer E2E Owner',
                'password' => Hash::make('password'),
                'role' => 'seller',
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return $ownerId;
    }

    private function tenant(string $ownerId, ?int $categoryId, ?int $cityId, mixed $now): string
    {
        $existing = DB::table('tenants')->where('slug', self::TENANT_SLUG)->first();
        $tenantId = (string) ($existing->id ?? Str::uuid());

        DB::table('tenants')->updateOrInsert(
            ['slug' => self::TENANT_SLUG],
            $this->onlyExistingColumns('tenants', [
                'id' => $tenantId,
                'name' => 'Dovra Buyer Conversion E2E',
                'description' => 'Synthetic buyer conversion fixture for local verification.',
                'user_id' => $ownerId,
                'category_id' => $categoryId,
                'city_id' => $cityId,
                'status' => 'approved',
                'verification_status' => 'verified',
                'verified_at' => $now,
                'is_active' => true,
                'is_paused' => false,
                'publication_paused_at' => null,
                'plan' => 'PRO',
                'plan_type' => 'pro',
                'subscription_status' => 'active',
                'subscription_ends_at' => null,
                'is_pro' => true,
                'product_limit' => 100,
                'business_type' => 'retail',
                'commercial_mode' => 'products',
                'rubro_key' => 'retail-core',
                'tags' => json_encode(['buyer-e2e', 'honduras'], JSON_THROW_ON_ERROR),
                'whatsapp_number' => null,
                'phone' => null,
                'email' => self::OWNER_EMAIL,
                'address' => 'Avenida E2E 2026, Tegucigalpa',
                'payment_methods' => json_encode([], JSON_THROW_ON_ERROR),
                'delivery_enabled' => false,
                'pickup_enabled' => true,
                'minimum_order' => 0,
                'brand_color' => '#0ea5e9',
                'primary_color' => '#0ea5e9',
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return $tenantId;
    }

    private function product(string $tenantId, ?int $categoryId, ?int $cityId, mixed $now): void
    {
        DB::table('products')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'slug' => self::PRODUCT_SLUG,
            ],
            $this->onlyExistingColumns('products', [
                'category_id' => $categoryId,
                'city_id' => $cityId,
                'name' => 'Producto Buyer E2E',
                'description' => 'Producto sintético de compra para verificación local.',
                'price' => '125.50',
                'currency' => self::MARKET_CURRENCY,
                'is_active' => true,
                'is_visible' => true,
                'is_locked' => false,
                'is_featured' => false,
                'manage_stock' => true,
                'stock_quantity' => 10,
                'low_stock_threshold' => 2,
                'allow_backorders' => false,
                'stock_status' => 'in_stock',
                'status' => 'published',
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );
    }

    /** @param array<string, mixed> $attributes */
    private function onlyExistingColumns(string $table, array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn (mixed $value, string $column): bool => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
