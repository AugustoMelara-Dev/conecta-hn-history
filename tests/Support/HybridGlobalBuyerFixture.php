<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Availability\AvailabilityRuleWriter;
use App\Application\Availability\Commands\ReplaceAvailabilityRulesCommand;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Location;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A guarded, idempotent, test-only Canada hybrid buyer fixture.
 *
 * This class is deliberately outside the application namespace. It creates
 * only the canonical rows needed by the two public buyer branches and uses
 * AvailabilityRuleWriter for all availability mutations.
 */
final class HybridGlobalBuyerFixture
{
    public const TENANT_SLUG = 'dovra-hybrid-global-buyer-e2e';

    public const OWNER_EMAIL = 'dovra.hybrid.global.buyer.e2e@example.test';

    public const PRODUCT_NAME = 'Canada Hybrid Product E2E';

    public const PRODUCT_SLUG = 'canada-hybrid-product-e2e';

    public const SERVICE_NAME = 'Canada Hybrid Service E2E';

    public const PROVIDER_NAME = 'Canada Hybrid Provider E2E';

    public const MARKET_COUNTRY = 'CA';

    public const MARKET_LOCALE = 'en-CA';

    public const MARKET_CURRENCY = 'CAD';

    public const MARKET_TIMEZONE = 'America/Toronto';

    public const PRODUCT_PRICE = '125.50';

    public const SERVICE_PRICE = '350.00';

    public const SERVICE_PRICE_MINOR = 35000;

    public static function seed(): array
    {
        self::guardRuntime();
        self::assertSchema();

        return DB::transaction(function (): array {
            $now = now();
            $ownerId = self::owner($now);
            $tenantId = self::tenant($ownerId, $now);

            DB::table('users')->where('id', $ownerId)->update(
                self::existingColumns('users', [
                    'tenant_id' => $tenantId,
                    'updated_at' => $now,
                ]),
            );

            $location = self::market($tenantId, $now);
            $productId = self::product($tenantId, $now);
            $serviceId = self::service($tenantId, $now);
            $providerId = self::provider($tenantId, $now);
            self::pivots($tenantId, $serviceId, $providerId, (string) $location->id, $now);
            self::availability($tenantId, $ownerId, $serviceId, $providerId, (string) $location->id);

            return [
                'owner_id' => $ownerId,
                'tenant_id' => $tenantId,
                'tenant_slug' => self::TENANT_SLUG,
                'location_id' => (string) $location->id,
                'product_id' => $productId,
                'service_id' => $serviceId,
                'provider_id' => $providerId,
                'market' => [
                    'country' => self::MARKET_COUNTRY,
                    'locale' => self::MARKET_LOCALE,
                    'currency' => self::MARKET_CURRENCY,
                    'timezone' => self::MARKET_TIMEZONE,
                ],
            ];
        });
    }

    public static function nextLocalDate(): string
    {
        return CarbonImmutable::now(self::MARKET_TIMEZONE)->addDay()->format('Y-m-d');
    }

    /** @return array<string, int> */
    public static function conversionCounts(string $tenantId): array
    {
        return [
            'orders' => (int) DB::table('orders')->where('tenant_id', $tenantId)->count(),
            'order_items' => (int) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.tenant_id', $tenantId)
                ->count(),
            'appointments' => (int) DB::table('appointments')->where('tenant_id', $tenantId)->count(),
            'inventory_movements' => (int) DB::table('inventory_movements')->where('tenant_id', $tenantId)->count(),
            // AvailabilityRuleWriter records its canonical setup operation in
            // the outbox.  Conversion counts intentionally include only the
            // two buyer conversion events, so setup evidence is not mistaken
            // for an order or reservation.
            'outbox_messages' => DB::table('outbox_messages')
                ->get()
                ->filter(function (object $message) use ($tenantId): bool {
                    $payload = is_array($message->payload ?? null)
                        ? $message->payload
                        : (json_decode((string) ($message->payload ?? ''), true) ?: []);
                    $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
                    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                    $payloadTenant = (string) ($meta['tenant_id'] ?? $data['tenantId'] ?? $data['tenant_id'] ?? '');
                    $eventName = (string) ($data['eventName'] ?? $data['event_name'] ?? '');

                    return $payloadTenant === $tenantId
                        && in_array($eventName, ['order.created', 'reservation.created'], true);
                })
                ->count(),
        ];
    }

    private static function guardRuntime(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('The synthetic Canada hybrid fixture is restricted to local/testing.');
        }

        if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER') !== 'true') {
            throw new \LogicException('Synthetic Canada hybrid fixture requires an explicit local guard.');
        }

        if ((string) config('database.default') !== 'sqlite') {
            throw new \LogicException('Synthetic Canada hybrid fixture requires SQLite.');
        }

        $database = (string) config('database.connections.sqlite.database');
        $expected = (string) getenv('DOVRA_E2E_DATABASE_PATH');
        if ($database === '' || $database === ':memory:' || $expected === '' || $expected === ':memory:') {
            throw new \LogicException('Synthetic Canada hybrid fixture requires a file-backed disposable SQLite database.');
        }

        $databasePath = realpath($database);
        $expectedPath = realpath($expected);
        if ($databasePath === false || $expectedPath === false || $databasePath !== $expectedPath || ! is_file($databasePath)) {
            throw new \LogicException('Synthetic Canada hybrid fixture database path mismatch.');
        }
    }

    private static function assertSchema(): void
    {
        foreach ([
            'users',
            'tenants',
            'tenant_markets',
            'market_packs',
            'market_pack_versions',
            'locations',
            'products',
            'services',
            'service_providers',
            'service_locations',
            'service_provider_locations',
            'availability_rules',
            'availability_exceptions',
            'availability_write_operations',
            'orders',
            'order_items',
            'appointments',
            'inventory_movements',
            'outbox_messages',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \LogicException("{$table} canonical hybrid evidence table is missing.");
            }
        }
    }

    private static function owner(mixed $now): string
    {
        $existing = DB::table('users')->where('email', self::OWNER_EMAIL)->first();
        $ownerId = (string) ($existing->id ?? Str::uuid());

        DB::table('users')->updateOrInsert(
            ['email' => self::OWNER_EMAIL],
            self::existingColumns('users', [
                'id' => $ownerId,
                'name' => 'Canada Hybrid Global Buyer Owner',
                'password' => Hash::make('password'),
                'role' => 'seller',
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return $ownerId;
    }

    private static function tenant(string $ownerId, mixed $now): string
    {
        $existing = DB::table('tenants')->where('slug', self::TENANT_SLUG)->first();
        $tenantId = (string) ($existing->id ?? Str::uuid());

        DB::table('tenants')->updateOrInsert(
            ['slug' => self::TENANT_SLUG],
            self::existingColumns('tenants', [
                'id' => $tenantId,
                'name' => 'Dovra Hybrid Global Buyer E2E',
                'description' => 'Synthetic Canada hybrid buyer continuity fixture for local verification.',
                'user_id' => $ownerId,
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
                'business_type' => BusinessType::SERVICE->value,
                'commercial_mode' => 'hybrid',
                'rubro_key' => 'hybrid-core',
                'tags' => json_encode(['hybrid-global-buyer', 'canada'], JSON_THROW_ON_ERROR),
                'whatsapp_number' => null,
                'phone' => null,
                'email' => self::OWNER_EMAIL,
                'address' => '100 King Street, Toronto',
                'payment_methods' => json_encode([], JSON_THROW_ON_ERROR),
                'delivery_enabled' => false,
                'pickup_enabled' => true,
                'minimum_order' => 0,
                'brand_color' => '#0f766e',
                'primary_color' => '#0f766e',
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return $tenantId;
    }

    private static function market(string $tenantId, mixed $now): Location
    {
        $markets = DB::table('tenant_markets')->where('tenant_id', $tenantId)->get();
        $locations = DB::table('locations')->where('tenant_id', $tenantId)->get();
        $primaryLocations = $locations->where('is_primary', true);

        if ($markets->isEmpty() && $locations->isEmpty()) {
            $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
            return GlobalMarketFixture::assignCanada($tenant);
        }

        if ($markets->count() !== 1 || $locations->count() !== 1 || $primaryLocations->count() !== 1) {
            throw new \LogicException('Existing Canada hybrid market state is partial or has duplicate primary locations.');
        }

        $market = $markets->sole();
        $location = $locations->sole();
        if ((string) $market->country_code !== self::MARKET_COUNTRY
            || (string) $market->locale_code !== self::MARKET_LOCALE
            || (string) $market->currency_code !== self::MARKET_CURRENCY
            || (string) $market->timezone_name !== self::MARKET_TIMEZONE
            || (bool) $market->is_primary !== true
            || (string) $location->country_code !== self::MARKET_COUNTRY
            || (string) $location->locale_code !== self::MARKET_LOCALE
            || (string) $location->currency_code !== self::MARKET_CURRENCY
            || (string) $location->timezone_name !== self::MARKET_TIMEZONE
            || (string) $location->name !== 'Toronto'
            || (string) $location->address !== '100 King Street'
            || (bool) $location->is_primary !== true
            || (bool) $location->is_active !== true) {
            throw new \LogicException('Existing Canada hybrid market state conflicts with the canonical facts.');
        }

        return Location::query()->withoutGlobalScopes()->findOrFail((string) $location->id);
    }

    private static function product(string $tenantId, mixed $now): int
    {
        $existing = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('slug', self::PRODUCT_SLUG)
            ->whereNull('deleted_at')
            ->first();
        if ($existing !== null) {
            if ((string) $existing->name !== self::PRODUCT_NAME
                || (float) $existing->price !== (float) self::PRODUCT_PRICE
                || (Schema::hasColumn('products', 'price_minor') && (int) $existing->price_minor !== 12550)
                || (Schema::hasColumn('products', 'currency') && (string) $existing->currency !== self::MARKET_CURRENCY)
                || (int) $existing->stock_quantity !== 10
                || (bool) $existing->is_active !== true
                || (bool) $existing->is_visible !== true) {
                throw new \LogicException('Existing Canada hybrid product conflicts with the canonical fixture.');
            }

            return (int) $existing->id;
        }

        $attributes = self::existingColumns('products', [
            'tenant_id' => $tenantId,
            'slug' => self::PRODUCT_SLUG,
            'name' => self::PRODUCT_NAME,
            'description' => 'Synthetic finite-stock Canada hybrid product.',
            'price' => self::PRODUCT_PRICE,
            'price_minor' => 12550,
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
            'published_at' => $now,
            'updated_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('products')->insert($attributes);

        return (int) DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('slug', self::PRODUCT_SLUG)
            ->value('id');
    }

    private static function service(string $tenantId, mixed $now): int
    {
        $existing = DB::table('services')
            ->where('tenant_id', $tenantId)
            ->where('name', self::SERVICE_NAME)
            ->first();
        if ($existing !== null) {
            if ((int) $existing->duration_minutes !== 30
                || (float) $existing->price !== (float) self::SERVICE_PRICE
                || (Schema::hasColumn('services', 'price_minor') && (int) $existing->price_minor !== self::SERVICE_PRICE_MINOR)
                || (Schema::hasColumn('services', 'currency') && (string) $existing->currency !== self::MARKET_CURRENCY)
                || (bool) $existing->is_active !== true) {
                throw new \LogicException('Existing Canada hybrid service conflicts with the canonical fixture.');
            }

            return (int) $existing->id;
        }

        DB::table('services')->insert(self::existingColumns('services', [
            'tenant_id' => $tenantId,
            'name' => self::SERVICE_NAME,
            'description' => 'Synthetic Canada hybrid appointment service.',
            'duration_minutes' => 30,
            'price' => self::SERVICE_PRICE,
            'price_minor' => self::SERVICE_PRICE_MINOR,
            'currency' => self::MARKET_CURRENCY,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        return (int) DB::table('services')
            ->where('tenant_id', $tenantId)
            ->where('name', self::SERVICE_NAME)
            ->value('id');
    }

    private static function provider(string $tenantId, mixed $now): int
    {
        $existing = DB::table('service_providers')
            ->where('tenant_id', $tenantId)
            ->where('name', self::PROVIDER_NAME)
            ->first();
        if ($existing !== null) {
            if ((bool) $existing->is_active !== true) {
                throw new \LogicException('Existing Canada hybrid provider is inactive.');
            }

            return (int) $existing->id;
        }

        DB::table('service_providers')->insert(self::existingColumns('service_providers', [
            'tenant_id' => $tenantId,
            'name' => self::PROVIDER_NAME,
            'email' => 'canada.hybrid.provider.e2e@example.test',
            'phone' => null,
            'schedule_config' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        return (int) DB::table('service_providers')
            ->where('tenant_id', $tenantId)
            ->where('name', self::PROVIDER_NAME)
            ->value('id');
    }

    private static function pivots(
        string $tenantId,
        int $serviceId,
        int $providerId,
        string $locationId,
        mixed $now,
    ): void {
        DB::table('service_locations')->updateOrInsert(
            ['tenant_id' => $tenantId, 'service_id' => $serviceId, 'location_id' => $locationId],
            self::existingColumns('service_locations', ['created_at' => $now, 'updated_at' => $now]),
        );
        DB::table('service_provider_locations')->updateOrInsert(
            ['tenant_id' => $tenantId, 'service_provider_id' => $providerId, 'location_id' => $locationId],
            self::existingColumns('service_provider_locations', ['created_at' => $now, 'updated_at' => $now]),
        );
    }

    private static function availability(
        string $tenantId,
        string $ownerId,
        int $serviceId,
        int $providerId,
        string $locationId,
    ): void {
        $rules = DB::table('availability_rules')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('service_provider_id', $providerId)
            ->where('source', 'canonical')
            ->where('is_active', true)
            ->get();
        $exceptions = DB::table('availability_exceptions')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('service_id', $serviceId)
            ->where('service_provider_id', $providerId)
            ->get();

        if ($rules->isEmpty() && $exceptions->isEmpty()) {
            $date = self::nextLocalDate();
            app(AvailabilityRuleWriter::class)->replace(new ReplaceAvailabilityRulesCommand(
                tenantId: $tenantId,
                actorId: $ownerId,
                idempotencyKey: 'dovra-hybrid-global-buyer:availability:'.$date,
                locationId: $locationId,
                serviceId: $serviceId,
                providerId: $providerId,
                rules: array_map(
                    static fn (int $weekday): array => [
                        'weekday' => $weekday,
                        'starts_at_local' => '09:00',
                        'ends_at_local' => '17:00',
                        'effective_from' => CarbonImmutable::now(self::MARKET_TIMEZONE)->format('Y-m-d'),
                        'effective_until' => null,
                    ],
                    range(1, 7),
                ),
                exceptions: [[
                    'local_date' => $date,
                    'kind' => 'open',
                    'starts_at_local' => '09:00',
                    'ends_at_local' => '17:00',
                    'reason' => 'Synthetic Canada hybrid buyer slot',
                ]],
            ));

            return;
        }

        if ($rules->count() !== 7 || $exceptions->count() !== 1) {
            throw new \LogicException('Existing Canada hybrid availability is partial or conflicting.');
        }

        foreach ($rules as $rule) {
            if ((int) $rule->weekday < 1 || (int) $rule->weekday > 7
                || (string) $rule->starts_at_local !== '09:00'
                || (string) $rule->ends_at_local !== '17:00') {
                throw new \LogicException('Existing Canada hybrid availability rule conflicts with the canonical fixture.');
            }
        }
        if ((string) $exceptions->sole()->kind !== 'open'
            || (string) $exceptions->sole()->local_date !== self::nextLocalDate()) {
            throw new \LogicException('Existing Canada hybrid availability exception conflicts with the canonical fixture.');
        }
    }

    /** @param array<string, mixed> $attributes */
    private static function existingColumns(string $table, array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn (mixed $value, string $column): bool => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
