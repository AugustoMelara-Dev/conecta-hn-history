<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Enums\StockStatus;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * @return array<int, string>
 */
function productTableColumns(): array
{
    return Schema::getColumnListing('products');
}

/**
 * @return array<int, string>
 */
function tenantTableColumns(): array
{
    return Schema::getColumnListing('tenants');
}

$fixture = [
    'tenant_slug' => 'demo-ai-guide-hn',
    'tenant_name' => 'Demo AI Guide HN',
    'owner_name' => 'Owner Demo AI Guide',
    'owner_email' => 'owner-demo-ai-guide-hn@vitobusinessos.app',
    'owner_password' => getenv('DEMO_OWNER_PASSWORD') !== false
        ? (string) getenv('DEMO_OWNER_PASSWORD')
        : 'ChangeMe123!',
];

$products = [
    [
        'name' => 'Combo Presupuesto 120 HNL',
        'slug' => 'demo-ai-guide-hn-budget-120',
        'description' => 'Combo economico para compras de bajo presupuesto.',
        'price' => 120.00,
        'stock_status' => StockStatus::InStock->value,
        'stock_quantity' => 20,
    ],
    [
        'name' => 'Combo Presupuesto 350 HNL',
        'slug' => 'demo-ai-guide-hn-budget-350',
        'description' => 'Combo balanceado para compras de presupuesto medio.',
        'price' => 350.00,
        'stock_status' => StockStatus::LowStock->value,
        'stock_quantity' => 8,
    ],
    [
        'name' => 'Combo Presupuesto 950 HNL',
        'slug' => 'demo-ai-guide-hn-budget-950',
        'description' => 'Combo premium para compras de presupuesto alto.',
        'price' => 950.00,
        'stock_status' => StockStatus::InStock->value,
        'stock_quantity' => 5,
    ],
];

$productsTableColumns = productTableColumns();
$tenantsTableColumns = tenantTableColumns();
$now = now();

DB::transaction(function () use ($fixture, $products, $productsTableColumns, $tenantsTableColumns, $now): void {
    $city = DB::table('cities')
        ->where('is_active', true)
        ->whereIn('slug', ['tegucigalpa', 'san-pedro-sula', 'la-ceiba'])
        ->orderByRaw("FIELD(slug, 'tegucigalpa', 'san-pedro-sula', 'la-ceiba')")
        ->first();

    if ($city === null) {
        $cityId = DB::table('cities')->insertGetId([
            'name' => 'Tegucigalpa',
            'slug' => 'tegucigalpa',
            'is_active' => true,
            'is_featured' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $city = DB::table('cities')->where('id', $cityId)->first();
    }

    $category = DB::table('categories')
        ->where('is_active', true)
        ->whereIn('slug', ['restaurantes', 'comida', 'servicios', 'tecnologia'])
        ->orderByRaw("FIELD(slug, 'restaurantes', 'comida', 'servicios', 'tecnologia')")
        ->first();

    if ($category === null) {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Restaurantes',
            'slug' => 'restaurantes',
            'description' => 'Categoria base para el fixture persistente del directorio IA.',
            'is_active' => true,
            'is_featured' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $category = DB::table('categories')->where('id', $categoryId)->first();
    }

    if ($city === null || $category === null) {
        throw new RuntimeException('No se pudo resolver city/category para el fixture demo.');
    }

    $owner = DB::table('users')->where('email', $fixture['owner_email'])->first();

    if ($owner === null) {
        $ownerId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $ownerId,
            'name' => $fixture['owner_name'],
            'email' => $fixture['owner_email'],
            'email_verified_at' => $now,
            'password' => Hash::make($fixture['owner_password']),
            'role' => UserRole::TENANT_OWNER->value,
            'tenant_id' => null,
            'phone' => '+50499990000',
            'remember_token' => Str::random(60),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $owner = DB::table('users')->where('id', $ownerId)->first();
    } else {
        DB::table('users')
            ->where('id', $owner->id)
            ->update([
                'name' => $fixture['owner_name'],
                'email_verified_at' => $owner->email_verified_at ?? $now,
                'password' => Hash::make($fixture['owner_password']),
                'role' => UserRole::TENANT_OWNER->value,
                'updated_at' => $now,
            ]);
        $owner = DB::table('users')->where('id', $owner->id)->first();
    }

    if ($owner === null) {
        throw new RuntimeException('No se pudo crear/actualizar el owner del fixture demo.');
    }

    $tenant = DB::table('tenants')->where('slug', $fixture['tenant_slug'])->first();
    $tenantId = $tenant !== null ? (string) $tenant->id : (string) Str::uuid();

    $tenantPayload = [
        'user_id' => (string) $owner->id,
        'name' => $fixture['tenant_name'],
        'slug' => $fixture['tenant_slug'],
        'city_id' => (int) $city->id,
        'category_id' => (int) $category->id,
        'is_active' => true,
        'updated_at' => $now,
    ];

    if (in_array('status', $tenantsTableColumns, true)) {
        $tenantPayload['status'] = 'approved';
    }
    if (in_array('subscription_status', $tenantsTableColumns, true)) {
        $tenantPayload['subscription_status'] = 'active';
    }
    if (in_array('verification_status', $tenantsTableColumns, true)) {
        $tenantPayload['verification_status'] = 'verified';
    }
    if (in_array('verified_at', $tenantsTableColumns, true)) {
        $tenantPayload['verified_at'] = $now;
    }
    if (in_array('publication_paused_at', $tenantsTableColumns, true)) {
        $tenantPayload['publication_paused_at'] = null;
    }
    if (in_array('plan_type', $tenantsTableColumns, true)) {
        $tenantPayload['plan_type'] = 'pro';
    }
    if (in_array('plan', $tenantsTableColumns, true)) {
        $tenantPayload['plan'] = 'PRO';
    }
    if (in_array('business_type', $tenantsTableColumns, true)) {
        $tenantPayload['business_type'] = 'restaurant';
    }
    if (in_array('business_segment_key', $tenantsTableColumns, true)) {
        $tenantPayload['business_segment_key'] = 'restaurant_fastcasual';
    }
    if (in_array('description', $tenantsTableColumns, true)) {
        $tenantPayload['description'] = 'Tenant demo persistente para validar Directorio + IA RAG por presupuesto.';
    }
    if (in_array('whatsapp_number', $tenantsTableColumns, true)) {
        $tenantPayload['whatsapp_number'] = '+50499990000';
    }
    if (in_array('phone', $tenantsTableColumns, true)) {
        $tenantPayload['phone'] = '+50499990000';
    }
    if (in_array('email', $tenantsTableColumns, true)) {
        $tenantPayload['email'] = $fixture['owner_email'];
    }

    if ($tenant === null) {
        $tenantPayload['id'] = $tenantId;
        $tenantPayload['created_at'] = $now;
        DB::table('tenants')->insert($tenantPayload);
    } else {
        DB::table('tenants')
            ->where('id', $tenantId)
            ->update($tenantPayload);
    }

    DB::table('users')
        ->where('id', $owner->id)
        ->update([
            'tenant_id' => $tenantId,
            'updated_at' => $now,
        ]);

    $membership = DB::table('tenant_memberships')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $owner->id)
        ->first();

    if ($membership === null) {
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'accepted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } else {
        DB::table('tenant_memberships')
            ->where('id', $membership->id)
            ->update([
                'role' => 'owner',
                'accepted_at' => $membership->accepted_at ?? $now,
                'updated_at' => $now,
            ]);
    }

    if (Schema::hasTable('tenant_onboarding_states')) {
        DB::table('tenant_onboarding_states')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'user_id' => $owner->id,
                'role' => 'seller',
            ],
            [
                'progress' => 100,
                'next_action_key' => null,
                'next_action_title' => 'Onboarding completo',
                'next_action_copy' => 'Fixture persistente listo para auditoria de directorio y guia IA.',
                'next_action_href' => '/app',
                'milestones' => json_encode([
                    'profile' => true,
                    'catalog' => true,
                    'payments' => true,
                    'launch' => true,
                ], JSON_THROW_ON_ERROR),
                'last_completed_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    $menuSection = DB::table('menu_sections')
        ->where('tenant_id', $tenantId)
        ->where('name', 'Destacados IA')
        ->first();

    if ($menuSection === null) {
        $menuSectionId = DB::table('menu_sections')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Destacados IA',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } else {
        $menuSectionId = (int) $menuSection->id;
        DB::table('menu_sections')
            ->where('id', $menuSectionId)
            ->update([
                'is_active' => true,
                'updated_at' => $now,
            ]);
    }

    foreach ($products as $item) {
        $product = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('slug', $item['slug'])
            ->first();

        $payload = [
            'tenant_id' => $tenantId,
            'city_id' => (int) $city->id,
            'category_id' => (int) $category->id,
            'menu_section_id' => $menuSectionId,
            'name' => $item['name'],
            'slug' => $item['slug'],
            'description' => $item['description'],
            'price' => $item['price'],
            'is_active' => true,
            'is_visible' => true,
            'is_featured' => true,
            'updated_at' => $now,
        ];

        if (in_array('stock_status', $productsTableColumns, true)) {
            $payload['stock_status'] = $item['stock_status'];
        }
        if (in_array('manage_stock', $productsTableColumns, true)) {
            $payload['manage_stock'] = true;
        }
        if (in_array('stock_quantity', $productsTableColumns, true)) {
            $payload['stock_quantity'] = $item['stock_quantity'];
        }
        if (in_array('allow_backorders', $productsTableColumns, true)) {
            $payload['allow_backorders'] = false;
        }
        if (in_array('status', $productsTableColumns, true)) {
            $payload['status'] = 'approved';
        }

        if ($product === null) {
            $payload['created_at'] = $now;
            DB::table('products')->insert($payload);
        } else {
            DB::table('products')
                ->where('id', $product->id)
                ->update($payload);
        }
    }
});

$tenant = DB::table('tenants')->where('slug', $fixture['tenant_slug'])->first();
$tenantId = $tenant !== null ? (string) $tenant->id : null;

$eligibleStock = [
    StockStatus::InStock->value,
    StockStatus::LowStock->value,
    StockStatus::OnBackorder->value,
    StockStatus::PreOrder->value,
];

$eligibleProducts = 0;
if ($tenantId !== null) {
    $eligibleProducts = (int) DB::table('products')
        ->where('tenant_id', $tenantId)
        ->where('is_visible', true)
        ->whereIn('stock_status', $eligibleStock)
        ->count();
}

$out = [
    'ok' => true,
    'tenant_slug' => $fixture['tenant_slug'],
    'tenant_id' => $tenantId,
    'owner_email' => $fixture['owner_email'],
    'eligible_products' => $eligibleProducts,
    'timestamp' => now()->toIso8601String(),
];

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
