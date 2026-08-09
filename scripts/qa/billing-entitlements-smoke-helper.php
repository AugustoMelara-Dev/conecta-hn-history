<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
$runId = (string) ($argv[1] ?? '');
$email = $runId.'@conectahn.test';

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = new User;
$user->forceFill([
    'name' => 'Owner Billing Certification',
    'email' => $email,
    'password' => Hash::make('ChangeMe123!'),
    'role' => 'seller',
    'email_verified_at' => now(),
]);
User::withoutEvents(fn () => $user->save());

$tenant = new Tenant;
$tenant->forceFill(array_filter([
    'id' => (string) Str::uuid(),
    'name' => 'Negocio PRO Certificado',
    'slug' => $runId,
    'user_id' => $user->id,
    'is_active' => true,
    'status' => 'active',
    'plan_type' => 'pro',
    'subscription_status' => 'active',
    'subscription_ends_at' => now()->addDays(21),
    'business_type' => 'retail',
    'commercial_mode' => 'products',
    'product_limit' => 100,
    'ai_balance' => 1.35,
    'ai_reserved_balance' => 0,
], fn ($value, string $column): bool => Schema::hasColumn('tenants', $column), ARRAY_FILTER_USE_BOTH));
Tenant::withoutEvents(fn () => $tenant->save());
$user->forceFill(['tenant_id' => $tenant->id])->save();

DB::table('ai_credit_reservations')->insert([
    'id' => bin2hex(random_bytes(16)),
    'tenant_id' => (string) $tenant->id,
    'operation' => 'billing_certification_fixture',
    'estimated_cost' => 13.65,
    'actual_cost' => 13.65,
    'denomination' => 'commercial_credits',
    'pricing_version' => (string) config('ai.commercial_credits.pricing_version', 'credits-v1'),
    'credits_per_provider_usd' => (float) config('ai.commercial_credits.credits_per_provider_usd', 20),
    'minimum_charge' => (float) config('ai.commercial_credits.minimum_charge', 0.01),
    'status' => 'settled',
    'usage_metadata' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
    'settled_at' => now(),
    'created_at' => now(),
    'updated_at' => now(),
]);

echo json_encode([
    'email' => $email,
    'password' => 'ChangeMe123!',
    'slug' => $tenant->slug,
    'tenantId' => $tenant->id,
], JSON_THROW_ON_ERROR);
