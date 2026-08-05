<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const DOVRA_BUYER_CONVERSION_SLUG = 'dovra-buyer-conversion-e2e';
const DOVRA_BUYER_CONVERSION_OWNER = 'dovra.buyer.conversion.e2e@example.test';

$repositoryRoot = dirname(__DIR__, 3);
$fail = static function (string $message): never {
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
};

if ($argc !== 1) {
    $fail('buyer conversion inspector accepts no arguments');
}

$providedAppKey = getenv('APP_KEY');
if (! is_string($providedAppKey) || trim($providedAppKey) === '') {
    $fail('buyer conversion inspector requires an explicit APP_KEY');
}

try {
    require $repositoryRoot.'/vendor/autoload.php';
    $application = require $repositoryRoot.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    if (! $application->environment(['local', 'testing'])) {
        $fail('buyer conversion inspector is restricted to local/testing');
    }

    if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_BUYER_CONVERSION') !== 'true') {
        $fail('synthetic buyer conversion guard is missing');
    }

    if ((string) config('database.default') !== 'sqlite') {
        $fail('buyer conversion inspector requires sqlite');
    }

    $configuredDatabase = (string) config('database.connections.sqlite.database');
    $expectedDatabase = (string) getenv('DOVRA_E2E_DATABASE_PATH');
    if ($configuredDatabase === '' || $configuredDatabase === ':memory:'
        || $expectedDatabase === '' || $expectedDatabase === ':memory:') {
        $fail('buyer conversion inspector requires a file-backed disposable database');
    }

    $configuredRealPath = realpath($configuredDatabase);
    $expectedRealPath = realpath($expectedDatabase);
    if ($configuredRealPath === false || $expectedRealPath === false
        || $configuredRealPath !== $expectedRealPath) {
        $fail('buyer conversion inspector database path mismatch');
    }

    $tenant = DB::table('tenants')->where('slug', DOVRA_BUYER_CONVERSION_SLUG)->first();
    if ($tenant === null) {
        $fail('synthetic buyer tenant missing');
    }

    $tenantId = (string) $tenant->id;
    if ((bool) $tenant->is_active !== true
        || (Schema::hasColumn('tenants', 'publication_paused_at') && $tenant->publication_paused_at !== null)
        || (Schema::hasColumn('tenants', 'plan_type') && (string) $tenant->plan_type !== 'pro')
        || (Schema::hasColumn('tenants', 'is_pro') && (bool) $tenant->is_pro !== true)
        || (Schema::hasColumn('tenants', 'commercial_mode') && (string) $tenant->commercial_mode !== 'products')
        || (Schema::hasColumn('tenants', 'pickup_enabled') && (bool) $tenant->pickup_enabled !== true)
        || (Schema::hasColumn('tenants', 'delivery_enabled') && (bool) $tenant->delivery_enabled !== false)
        || (Schema::hasColumn('tenants', 'phone') && $tenant->phone !== null)
        || (Schema::hasColumn('tenants', 'whatsapp_number') && $tenant->whatsapp_number !== null)) {
        $fail('synthetic buyer tenant truth boundary is invalid');
    }

    $products = DB::table('products')
        ->where('tenant_id', $tenantId)
        ->where('name', 'Producto Buyer E2E')
        ->whereNull('deleted_at')
        ->get();
    if ($products->count() !== 1) {
        $fail('synthetic buyer product count is not exactly one');
    }

    $product = $products->sole();
    if ((float) $product->price !== 125.50
        || (int) $product->stock_quantity !== 9
        || (bool) $product->is_active !== true
        || (bool) $product->is_visible !== true
        || (Schema::hasColumn('products', 'status') && (string) $product->status !== 'published')
        || (Schema::hasColumn('products', 'currency') && (string) $product->currency !== 'HNL')) {
        $fail('synthetic buyer product snapshot is invalid');
    }

    $orders = DB::table('orders')->where('tenant_id', $tenantId)->get();
    if ($orders->count() !== 1) {
        $fail('synthetic buyer order count is not exactly one');
    }

    $order = $orders->sole();
    if ((string) $order->status !== 'pending'
        || (float) $order->subtotal !== 125.50
        || (float) $order->total !== 125.50
        || (Schema::hasColumn('orders', 'currency') && (string) $order->currency !== 'HNL')
        || (Schema::hasColumn('orders', 'delivery_type') && (string) $order->delivery_type !== 'pickup')
        || (Schema::hasColumn('orders', 'payment_status') && (string) $order->payment_status !== 'pending')
        || (Schema::hasColumn('orders', 'payment_method') && (string) $order->payment_method !== 'pending')
        || (string) $order->customer_name !== 'Buyer E2E'
        || (string) $order->customer_phone !== '9999-2026') {
        $fail('synthetic buyer order snapshot is invalid');
    }

    $items = DB::table('order_items')->where('order_id', $order->id)->get();
    if ($items->count() !== 1) {
        $fail('synthetic buyer order item count is not exactly one');
    }

    $item = $items->sole();
    if ((int) $item->product_id !== (int) $product->id
        || (string) $item->product_name !== 'Producto Buyer E2E'
        || (float) $item->unit_price !== 125.50
        || (float) $item->line_total !== 125.50
        || (int) $item->quantity !== 1) {
        $fail('synthetic buyer order item snapshot is invalid');
    }

    $movements = DB::table('inventory_movements')
        ->where('tenant_id', $tenantId)
        ->where('product_id', $product->id)
        ->where('reason', 'order_consumed')
        ->get();
    if ($movements->count() !== 1) {
        $fail('synthetic buyer inventory movement count is not exactly one');
    }

    $movement = $movements->sole();
    if ((int) $movement->delta !== -1
        || (int) $movement->previous_quantity !== 10
        || (int) $movement->resulting_quantity !== 9) {
        $fail('synthetic buyer inventory movement is invalid');
    }

    $outboxMatches = [];
    foreach (DB::table('outbox_messages')->where('type', 'like', '%BusinessOperationChanged')->get() as $message) {
        $payload = json_decode((string) $message->payload, true, 512, JSON_THROW_ON_ERROR);
        $payloadTenant = $payload['meta']['tenant_id']
            ?? $payload['data']['tenantId']
            ?? $payload['data']['tenant_id']
            ?? null;
        if ((string) $payloadTenant === $tenantId) {
            $outboxMatches[] = [
                'type' => (string) $message->type,
                'aggregate_id' => (string) ($payload['meta']['aggregate_id'] ?? $payload['data']['aggregateId'] ?? ''),
                'event_name' => (string) ($payload['data']['eventName'] ?? $payload['data']['event_name'] ?? ''),
            ];
        }
    }

    if (count($outboxMatches) !== 1
        || $outboxMatches[0]['aggregate_id'] !== 'order:'.$order->id
        || $outboxMatches[0]['event_name'] !== 'order.created') {
        $fail('synthetic buyer BusinessOperationChanged outbox evidence is invalid');
    }

    $billingTables = [
        'payments',
        'subscription_payments',
        'subscription_intents',
        'tenant_billing_references',
        'global_billing_entitlements',
        'global_billing_credit_accounts',
        'global_billing_credit_reservations',
        'global_billing_credit_ledger_entries',
        'global_billing_credit_alerts',
        'global_billing_credit_reconciliations',
        'global_billing_lifecycle_events',
    ];
    $billingCounts = [];
    foreach ($billingTables as $table) {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            continue;
        }

        $billingCounts[$table] = (int) DB::table($table)->where('tenant_id', $tenantId)->count();
    }

    if (array_sum($billingCounts) !== 0) {
        $fail('synthetic buyer fixture unexpectedly has billing/payment/subscription rows');
    }

    $foreignOrders = (int) DB::table('orders')->where('tenant_id', '<>', $tenantId)->count();
    if ($foreignOrders !== 0) {
        $fail('synthetic buyer fixture has foreign orders');
    }

    $result = [
        'status' => 'ok',
        'tenant_slug' => DOVRA_BUYER_CONVERSION_SLUG,
        'tenant_id' => $tenantId,
        'owner_email' => DOVRA_BUYER_CONVERSION_OWNER,
        'product' => [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'price' => (float) $product->price,
            'stock_quantity' => (int) $product->stock_quantity,
        ],
        'order' => [
            'id' => (int) $order->id,
            'number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'subtotal' => (float) $order->subtotal,
            'total' => (float) $order->total,
            'currency' => (string) ($order->currency ?? 'HNL'),
            'delivery_type' => (string) ($order->delivery_type ?? 'pickup'),
            'payment_status' => (string) ($order->payment_status ?? 'pending'),
        ],
        'order_items' => 1,
        'inventory' => [
            'movements' => 1,
            'delta' => (int) $movement->delta,
            'reason' => (string) $movement->reason,
        ],
        'outbox' => [
            'business_operation_changed' => count($outboxMatches),
            'event_name' => $outboxMatches[0]['event_name'],
        ],
        'billing_counts' => $billingCounts,
        'foreign_orders' => $foreignOrders,
    ];

    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
