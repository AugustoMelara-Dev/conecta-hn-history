<?php

declare(strict_types=1);

use App\Enums\StockStatus;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tenantSlug = 'demo-ai-guide-hn';
$tenant = DB::table('tenants as t')
    ->leftJoin('cities as c', 'c.id', '=', 't.city_id')
    ->leftJoin('categories as cat', 'cat.id', '=', 't.category_id')
    ->select([
        't.id',
        't.slug',
        't.name',
        't.status',
        't.is_active',
        't.subscription_status',
        't.deleted_at',
        'c.slug as city_slug',
        'cat.slug as category_slug',
    ])
    ->where('t.slug', $tenantSlug)
    ->first();

$eligibleProducts = 0;
$visibleProducts = 0;
$purchaseableProducts = 0;

if ($tenant !== null) {
    $visibleProducts = (int) DB::table('products')
        ->where('tenant_id', $tenant->id)
        ->where('is_visible', true)
        ->count();

    $purchaseableProducts = (int) DB::table('products')
        ->where('tenant_id', $tenant->id)
        ->whereIn('stock_status', [
            StockStatus::InStock->value,
            StockStatus::LowStock->value,
            StockStatus::OnBackorder->value,
            StockStatus::PreOrder->value,
        ])
        ->count();

    $eligibleProducts = (int) DB::table('products as p')
        ->join('tenants as t', 't.id', '=', 'p.tenant_id')
        ->where('p.tenant_id', $tenant->id)
        ->where('p.is_visible', true)
        ->whereIn('p.stock_status', [
            StockStatus::InStock->value,
            StockStatus::LowStock->value,
            StockStatus::OnBackorder->value,
            StockStatus::PreOrder->value,
        ])
        ->where('t.status', 'approved')
        ->where('t.is_active', true)
        ->whereIn('t.subscription_status', ['active', 'trial'])
        ->count();
}

$publishedTenants = (int) DB::table('tenants')
    ->where('status', 'approved')
    ->where('is_active', true)
    ->whereIn('subscription_status', ['active', 'trial'])
    ->count();

$searchSample = DB::table('tenants as t')
    ->leftJoin('cities as c', 'c.id', '=', 't.city_id')
    ->leftJoin('categories as cat', 'cat.id', '=', 't.category_id')
    ->where('t.status', 'approved')
    ->where('t.is_active', true)
    ->whereIn('t.subscription_status', ['active', 'trial'])
    ->orderBy('t.created_at', 'desc')
    ->limit(5)
    ->get(['t.slug', 'c.slug as city_slug', 'cat.slug as category_slug']);

$out = [
    'tenant' => $tenant,
    'visible_products' => $visibleProducts,
    'purchaseable_products' => $purchaseableProducts,
    'eligible_products' => $eligibleProducts,
    'published_tenants' => $publishedTenants,
    'published_sample' => $searchSample,
];

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
