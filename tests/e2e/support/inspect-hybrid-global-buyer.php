<?php

declare(strict_types=1);

use App\Application\Availability\AvailabilityQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const DOVRA_HYBRID_GLOBAL_BUYER_SLUG = 'dovra-hybrid-global-buyer-e2e';
const DOVRA_HYBRID_GLOBAL_BUYER_OWNER = 'dovra.hybrid.global.buyer.e2e@example.test';
const DOVRA_HYBRID_GLOBAL_BUYER_PRODUCT = 'Canada Hybrid Product E2E';
const DOVRA_HYBRID_GLOBAL_BUYER_SERVICE = 'Canada Hybrid Service E2E';
const DOVRA_HYBRID_GLOBAL_BUYER_PROVIDER = 'Canada Hybrid Provider E2E';
const DOVRA_HYBRID_GLOBAL_BUYER_TZ = 'America/Toronto';

$repositoryRoot = dirname(__DIR__, 3);
$fail = static function (string $message): never {
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
};

$stage = 'after-booking';
if ($argc > 2) {
    $fail('hybrid global buyer inspector accepts at most one --stage argument');
}
if ($argc === 2) {
    $stage = str_starts_with((string) $argv[1], '--stage=')
        ? substr((string) $argv[1], 8)
        : (string) $argv[1];
}
if (! in_array($stage, ['before-order', 'after-order', 'after-booking'], true)) {
    $fail('hybrid global buyer inspector stage is invalid');
}

$providedAppKey = getenv('APP_KEY');
if (! is_string($providedAppKey) || trim($providedAppKey) === '') {
    $fail('hybrid global buyer inspector requires an explicit APP_KEY');
}

$decodePayload = static function (mixed $raw): array {
    if (is_array($raw)) {
        return $raw;
    }
    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? $decoded : [];
};

$assertTable = static function (string $table) use ($fail): void {
    if (! Schema::hasTable($table)) {
        $fail("{$table} canonical hybrid evidence table is missing");
    }
};

$assertTenantColumn = static function (string $table) use ($fail): void {
    if (! Schema::hasColumn($table, 'tenant_id')) {
        $fail("{$table} canonical tenant-attributable table lacks tenant_id");
    }
};

$assertColumns = static function (string $table, array $columns) use ($fail): void {
    foreach ($columns as $column) {
        if (! Schema::hasColumn($table, $column)) {
            $fail("{$table}.{$column} canonical hybrid evidence column is missing");
        }
    }
};

try {
    // The inspector accepts only the required DB_CONNECTION=sqlite file-backed
    // environment; it never falls back to an in-memory database.
    require $repositoryRoot.'/vendor/autoload.php';
    $application = require $repositoryRoot.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    if (! $application->environment(['local', 'testing'])) {
        $fail('hybrid global buyer inspector is restricted to local/testing');
    }
    if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER') !== 'true') {
        $fail('synthetic hybrid global buyer guard is missing');
    }
    if ((string) config('database.default') !== 'sqlite') {
        $fail('hybrid global buyer inspector requires sqlite');
    }
    $configuredDatabase = (string) config('database.connections.sqlite.database');
    $expectedDatabase = (string) getenv('DOVRA_E2E_DATABASE_PATH');
    if ($configuredDatabase === '' || $configuredDatabase === ':memory:'
        || $expectedDatabase === '' || $expectedDatabase === ':memory:') {
        $fail('hybrid global buyer inspector requires a file-backed disposable database');
    }
    $configuredRealPath = realpath($configuredDatabase);
    $expectedRealPath = realpath($expectedDatabase);
    if ($configuredRealPath === false || $expectedRealPath === false
        || $configuredRealPath !== $expectedRealPath
        || ! is_file($configuredRealPath)
        || filesize($configuredRealPath) < 1) {
        $fail('hybrid global buyer inspector database path mismatch');
    }

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
        $assertTable($table);
    }
    foreach ([
        'tenants' => [
            'publication_paused_at', 'plan_type', 'is_pro', 'phone', 'whatsapp_number', 'payment_methods',
        ],
        'products' => ['price_minor', 'currency', 'allow_backorders'],
        'services' => ['price_minor', 'currency', 'duration_minutes'],
        'orders' => ['currency', 'delivery_type', 'payment_status', 'payment_method'],
        'order_items' => ['product_id', 'unit_price', 'quantity'],
        'appointments' => [
            'service_id', 'service_provider_id', 'location_id', 'status', 'source', 'currency',
            'price_minor', 'duration_minutes', 'timezone', 'local_date', 'scheduled_at',
        ],
        'inventory_movements' => [
            'tenant_id', 'product_id', 'delta', 'previous_quantity', 'resulting_quantity', 'reason',
        ],
        'outbox_messages' => ['type', 'payload'],
    ] as $table => $columns) {
        $assertColumns($table, $columns);
    }

    $tenantRows = DB::table('tenants')->where('slug', DOVRA_HYBRID_GLOBAL_BUYER_SLUG)->get();
    if ($tenantRows->count() !== 1) {
        $fail('hybrid global buyer tenant count is not exactly one');
    }
    $tenant = $tenantRows->sole();
    $tenantId = (string) $tenant->id;
    foreach (['orders', 'appointments', 'inventory_movements'] as $table) {
        $assertTenantColumn($table);
    }
    if ((string) $tenant->email !== DOVRA_HYBRID_GLOBAL_BUYER_OWNER
        || (string) $tenant->business_type !== 'service'
        || (string) $tenant->commercial_mode !== 'hybrid'
        || (string) $tenant->rubro_key !== 'hybrid-core'
        || (string) $tenant->status !== 'approved'
        || (string) $tenant->verification_status !== 'verified'
        || (bool) $tenant->is_active !== true
        || $tenant->publication_paused_at !== null
        || (string) $tenant->plan_type !== 'pro'
        || (bool) $tenant->is_pro !== true
        || (string) $tenant->phone !== ''
        || (string) $tenant->whatsapp_number !== ''
        || json_decode((string) $tenant->payment_methods, true) !== []) {
        $fail('hybrid global buyer tenant truth boundary is invalid');
    }

    $markets = DB::table('tenant_markets')->where('tenant_id', $tenantId)->get();
    if ($markets->count() !== 1) {
        $fail('hybrid global buyer tenant market count is not exactly one');
    }
    $market = $markets->sole();
    $locations = DB::table('locations')->where('tenant_id', $tenantId)->get();
    $primaryLocations = $locations->where('is_primary', true);
    if ($locations->count() !== 1 || $primaryLocations->count() !== 1) {
        $fail('hybrid global buyer Toronto location count is not exactly one');
    }
    $location = $locations->sole();
    if ((string) $market->country_code !== 'CA'
        || (string) $market->locale_code !== 'en-CA'
        || (string) $market->currency_code !== 'CAD'
        || (string) $market->timezone_name !== DOVRA_HYBRID_GLOBAL_BUYER_TZ
        || (bool) $market->is_primary !== true
        || (string) $location->country_code !== 'CA'
        || (string) $location->locale_code !== 'en-CA'
        || (string) $location->currency_code !== 'CAD'
        || (string) $location->timezone_name !== DOVRA_HYBRID_GLOBAL_BUYER_TZ
        || (string) $location->name !== 'Toronto'
        || (string) $location->address !== '100 King Street'
        || (bool) $location->is_primary !== true
        || (bool) $location->is_active !== true
    ) {
        $fail('hybrid global buyer Canada market/location truth boundary is invalid');
    }

    $marketPack = DB::table('market_packs')->where('key', 'ca')->first();
    $marketVersion = $marketPack === null ? null : DB::table('market_pack_versions')
        ->where('market_pack_id', $marketPack->id)
        ->where('version', 1)
        ->first();
    if ($marketPack === null || $marketVersion === null
        || (string) $marketVersion->status !== 'published'
        || (string) $marketVersion->default_locale_code !== 'en-CA'
        || (string) $marketVersion->default_currency_code !== 'CAD'
        || (string) $marketVersion->default_timezone_name !== DOVRA_HYBRID_GLOBAL_BUYER_TZ) {
        $fail('hybrid global buyer Canada market pack is invalid');
    }

    $products = DB::table('products')->where('tenant_id', $tenantId)
        ->where('slug', 'canada-hybrid-product-e2e')->whereNull('deleted_at')->get();
    if ($products->count() !== 1) {
        $fail('hybrid global buyer product count is not exactly one');
    }
    $product = $products->sole();
    $expectedStock = $stage === 'before-order' ? 10 : 9;
    if ((string) $product->name !== DOVRA_HYBRID_GLOBAL_BUYER_PRODUCT
        || (float) $product->price !== 125.50
        || (int) $product->price_minor !== 12550
        || (string) $product->currency !== 'CAD'
        || (int) $product->stock_quantity !== $expectedStock
        || (bool) $product->is_active !== true
        || (bool) $product->is_visible !== true
        || (bool) $product->allow_backorders !== false
    ) {
        $fail('hybrid global buyer product snapshot is invalid');
    }

    $services = DB::table('services')->where('tenant_id', $tenantId)
        ->where('name', DOVRA_HYBRID_GLOBAL_BUYER_SERVICE)->get();
    $providers = DB::table('service_providers')->where('tenant_id', $tenantId)
        ->where('name', DOVRA_HYBRID_GLOBAL_BUYER_PROVIDER)->get();
    if ($services->count() !== 1 || $providers->count() !== 1) {
        $fail('hybrid global buyer service/provider count is invalid');
    }
    $service = $services->sole();
    $provider = $providers->sole();
    if ((int) $service->duration_minutes !== 30
        || (float) $service->price !== 350.00
        || (int) $service->price_minor !== 35000
        || (string) $service->currency !== 'CAD'
        || (bool) $service->is_active !== true
        || (bool) $provider->is_active !== true
        || DB::table('service_locations')->where('tenant_id', $tenantId)->where('service_id', $service->id)
            ->where('location_id', $location->id)->count() !== 1
        || DB::table('service_provider_locations')->where('tenant_id', $tenantId)
            ->where('service_provider_id', $provider->id)->where('location_id', $location->id)->count() !== 1
    ) {
        $fail('hybrid global buyer service/provider snapshot is invalid');
    }

    $rules = DB::table('availability_rules')->where('tenant_id', $tenantId)
        ->where('location_id', $location->id)->where('service_id', $service->id)
        ->where('service_provider_id', $provider->id)->where('source', 'canonical')
        ->where('is_active', true)->orderBy('weekday')->get();
    $exceptions = DB::table('availability_exceptions')->where('tenant_id', $tenantId)
        ->where('location_id', $location->id)->where('service_id', $service->id)
        ->where('service_provider_id', $provider->id)->where('source', 'canonical')->get();
    if ($rules->count() !== 7 || $exceptions->count() !== 1) {
        $fail('hybrid global buyer canonical availability count is invalid');
    }
    foreach ($rules as $rule) {
        if ((int) $rule->weekday < 1 || (int) $rule->weekday > 7
            || (string) $rule->starts_at_local !== '09:00'
            || (string) $rule->ends_at_local !== '17:00'
            || (string) $rule->timezone !== DOVRA_HYBRID_GLOBAL_BUYER_TZ) {
            $fail('hybrid global buyer canonical availability rule is invalid');
        }
    }
    $exception = $exceptions->sole();
    if ((string) $exception->kind !== 'open'
        || (string) $exception->starts_at_local !== '09:00'
        || (string) $exception->ends_at_local !== '17:00'
        || (string) $exception->timezone !== DOVRA_HYBRID_GLOBAL_BUYER_TZ
        || (string) $exception->local_date !== CarbonImmutable::now(DOVRA_HYBRID_GLOBAL_BUYER_TZ)->addDay()->format('Y-m-d')) {
        $fail('hybrid global buyer canonical availability exception is invalid');
    }

    $orders = DB::table('orders')->where('tenant_id', $tenantId)->get();
    $appointments = DB::table('appointments')->where('tenant_id', $tenantId)->get();
    $expectedOrders = in_array($stage, ['after-order', 'after-booking'], true) ? 1 : 0;
    $expectedAppointments = $stage === 'after-booking' ? 1 : 0;
    if ($orders->count() !== $expectedOrders || $appointments->count() !== $expectedAppointments) {
        $fail('hybrid global buyer conversion row counts do not match inspector stage');
    }

    $order = null;
    $item = null;
    if ($expectedOrders === 1) {
        $order = $orders->sole();
        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        if ($items->count() !== 1) {
            $fail('hybrid global buyer order item count is not exactly one');
        }
        $item = $items->sole();
        if ((string) $order->status !== 'pending'
            || (float) $order->subtotal !== 125.50
            || (float) $order->total !== 125.50
            || (string) $order->currency !== 'CAD'
            || (string) $order->delivery_type !== 'pickup'
            || (string) $order->payment_status !== 'pending'
            || (string) $order->payment_method !== 'pending'
            || (int) $item->product_id !== (int) $product->id
            || (float) $item->unit_price !== 125.50
            || (int) $item->quantity !== 1
        ) {
            $fail('hybrid global buyer order snapshot is invalid');
        }
    } elseif (DB::table('order_items')->whereIn('order_id', $orders->pluck('id'))->count() !== 0) {
        $fail('hybrid global buyer has order items without an order conversion');
    }

    $movements = DB::table('inventory_movements')->where('tenant_id', $tenantId)->get();
    if ($movements->count() !== $expectedOrders) {
        $fail('hybrid global buyer inventory movement count is invalid');
    }
    $movement = null;
    if ($expectedOrders === 1) {
        $movement = $movements->sole();
        if ((int) $movement->product_id !== (int) $product->id
            || (int) $movement->delta !== -1
            || (int) $movement->previous_quantity !== 10
            || (int) $movement->resulting_quantity !== 9
            || (string) $movement->reason !== 'order_consumed') {
            $fail('hybrid global buyer inventory movement is invalid');
        }
    }

    $appointment = null;
    $selectedSlotUnavailable = null;
    if ($expectedAppointments === 1) {
        $appointment = $appointments->sole();
        if ((int) $appointment->service_id !== (int) $service->id
            || (int) $appointment->service_provider_id !== (int) $provider->id
            || (string) $appointment->location_id !== (string) $location->id
            || (string) $appointment->status !== 'pending'
            || (string) $appointment->source !== 'public_booking'
            || (string) $appointment->currency !== 'CAD'
            || (int) $appointment->price_minor !== 35000
            || (int) $appointment->duration_minutes !== 30
            || (string) $appointment->timezone !== DOVRA_HYBRID_GLOBAL_BUYER_TZ
        ) {
            $fail('hybrid global buyer appointment snapshot is invalid');
        }
        $localStart = CarbonImmutable::parse((string) $appointment->scheduled_at, 'UTC')
            ->setTimezone(DOVRA_HYBRID_GLOBAL_BUYER_TZ);
        $storedLocalDate = is_string($appointment->local_date) ? trim($appointment->local_date) : '';
        try {
            $storedLocalDate = $storedLocalDate === ''
                ? ''
                : CarbonImmutable::parse($storedLocalDate, DOVRA_HYBRID_GLOBAL_BUYER_TZ)->format('Y-m-d');
        } catch (Throwable) {
            $storedLocalDate = '';
        }
        if ($storedLocalDate !== $localStart->format('Y-m-d')) {
            $fail('hybrid global buyer appointment local date snapshot is invalid');
        }

        $selectedStartUtc = CarbonImmutable::parse((string) $appointment->scheduled_at, 'UTC');
        $availableSlots = app(AvailabilityQuery::class)->slots(
            $tenantId,
            (int) $appointment->service_id,
            (int) $appointment->service_provider_id,
            (string) $appointment->location_id,
            $localStart->startOfDay(),
        );
        $selectedSlotUnavailable = ! collect($availableSlots)->contains(
            static function (mixed $slot) use ($selectedStartUtc): bool {
                if (! is_array($slot) || ! is_string($slot['starts_at_utc'] ?? null)) {
                    return false;
                }

                try {
                    return CarbonImmutable::parse($slot['starts_at_utc'], 'UTC')->getTimestamp()
                        === $selectedStartUtc->getTimestamp();
                } catch (Throwable) {
                    return false;
                }
            },
        );
        if ($selectedSlotUnavailable !== true) {
            $fail('hybrid global buyer selected slot remains available after booking');
        }
    }

    $conversionOutbox = [];
    $businessOperationChangedType = 'App\\Domain\\Operations\\Events\\BusinessOperationChanged';
    foreach (DB::table('outbox_messages')->get() as $message) {
        $payload = $decodePayload($message->payload ?? null);
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $payloadTenant = (string) ($meta['tenant_id'] ?? $data['tenantId'] ?? $data['tenant_id'] ?? '');
        $aggregateId = (string) ($meta['aggregate_id'] ?? $data['aggregateId'] ?? $data['aggregate_id'] ?? '');
        $eventName = (string) ($data['eventName'] ?? $data['event_name'] ?? '');
        if (! in_array($eventName, ['order.created', 'reservation.created'], true)) {
            continue;
        }
        if ($payloadTenant !== $tenantId) {
            $fail('hybrid global buyer has foreign-tenant conversion outbox evidence');
        }
        if ((string) $message->type !== $businessOperationChangedType) {
            $fail('hybrid global buyer conversion outbox type is not BusinessOperationChanged');
        }
        $conversionOutbox[] = [
            'aggregate_id' => $aggregateId,
            'event_name' => $eventName,
            'type' => (string) $message->type,
        ];
    }
    $expectedOutbox = $expectedOrders + $expectedAppointments;
    if (count($conversionOutbox) !== $expectedOutbox) {
        $fail('hybrid global buyer conversion outbox count is invalid');
    }
    if ($expectedOrders === 1 && count(array_filter($conversionOutbox, static fn (array $event): bool => $event['aggregate_id'] === 'order:'.$order->id && $event['event_name'] === 'order.created')) !== 1) {
        $fail('hybrid global buyer order.created outbox evidence is invalid');
    }
    if ($expectedAppointments === 1 && count(array_filter($conversionOutbox, static fn (array $event): bool => $event['aggregate_id'] === 'appointment:'.$appointment->id && $event['event_name'] === 'reservation.created')) !== 1) {
        $fail('hybrid global buyer reservation.created outbox evidence is invalid');
    }

    foreach (['orders', 'appointments', 'inventory_movements'] as $table) {
        if (DB::table($table)->where('tenant_id', '<>', $tenantId)->count() !== 0) {
            $fail("hybrid global buyer has foreign {$table}");
        }
    }
    foreach (['payments', 'subscription_payments', 'subscription_intents', 'tenant_billing_references',
        'global_billing_entitlements', 'global_billing_credit_accounts', 'global_billing_credit_reservations',
        'global_billing_credit_ledger_entries', 'global_billing_credit_alerts', 'global_billing_credit_reconciliations',
        'global_billing_lifecycle_events', 'ai_credit_reservations', 'ai_credit_ledger_entries',
        'ai_credit_usage_alerts', 'ai_credit_grants'] as $table) {
        $assertTable($table);
        $assertTenantColumn($table);
        if (DB::table($table)->where('tenant_id', $tenantId)->count() !== 0) {
            $fail("hybrid global buyer has {$table} evidence");
        }
    }

    $availability = [
        'rules' => $rules->map(static fn (object $row): array => (array) $row)->values()->all(),
        'exceptions' => $exceptions->map(static fn (object $row): array => (array) $row)->values()->all(),
    ];
    echo json_encode([
        'status' => 'ok',
        'stage' => $stage,
        'tenant_slug' => DOVRA_HYBRID_GLOBAL_BUYER_SLUG,
        'tenant_id' => $tenantId,
        'owner_email' => DOVRA_HYBRID_GLOBAL_BUYER_OWNER,
        'tenant' => [
            'business_type' => (string) $tenant->business_type,
            'commercial_mode' => (string) $tenant->commercial_mode,
            'rubro_key' => (string) $tenant->rubro_key,
            'country' => (string) $market->country_code,
            'locale' => (string) $market->locale_code,
            'currency' => (string) $market->currency_code,
            'timezone' => (string) $market->timezone_name,
        ],
        'location_id' => (string) $location->id,
        'product' => [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'price' => (float) $product->price,
            'stock_quantity' => (int) $product->stock_quantity,
        ],
        'service' => [
            'id' => (int) $service->id,
            'name' => (string) $service->name,
            'price_minor' => (int) $service->price_minor,
            'duration_minutes' => (int) $service->duration_minutes,
            'provider_id' => (int) $provider->id,
        ],
        'order' => $order === null ? null : [
            'id' => (int) $order->id,
            'number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'payment_method' => (string) $order->payment_method,
            'payment_status' => (string) $order->payment_status,
            'currency' => (string) $order->currency,
        ],
        'appointment' => $appointment === null ? null : [
            'id' => (int) $appointment->id,
            'status' => (string) $appointment->status,
            'source' => (string) $appointment->source,
            'currency' => (string) $appointment->currency,
            'price_minor' => (int) $appointment->price_minor,
            'duration_minutes' => (int) $appointment->duration_minutes,
            'timezone' => (string) $appointment->timezone,
            'local_date' => (string) $appointment->local_date,
            'scheduled_at_utc' => (string) $appointment->scheduled_at,
        ],
        'inventory' => [
            'movements' => $movements->count(),
            'delta' => $movement === null ? null : (int) $movement->delta,
            'reason' => $movement === null ? null : (string) $movement->reason,
        ],
        'availability' => $availability,
        'selected_slot_unavailable' => $selectedSlotUnavailable,
        'outbox' => [
            'conversion_events' => $conversionOutbox,
            'setup_events_excluded' => (int) DB::table('outbox_messages')->count() - count($conversionOutbox),
        ],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
