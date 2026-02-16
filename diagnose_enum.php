<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

echo "=== DEEPER DIAGNOSTIC ===\n\n";

try {
    // Test 1: Create Tenant FIRST with raw DB to bypass all ORM issues
    echo "1. Creating Tenant via RAW DB...\n";
    $tenantId = (string) \Illuminate\Support\Str::uuid();
    DB::table('tenants')->insert([
        'id' => $tenantId,
        'user_id' => null,
        'name' => 'Test Tenant',
        'slug' => 'test-' . \Illuminate\Support\Str::random(6),
        'plan' => 'SEED',
        'is_active' => true,
        'plan_type' => 'semilla',
        'subscription_status' => 'active',
        'ai_balance' => 1.00,
        'ai_reserved_balance' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "   ✓ Tenant created: {$tenantId}\n\n";

    // Test 2: Create User with that tenant
    echo "2. Creating User via RAW DB...\n";
    $userId = (string) \Illuminate\Support\Str::uuid();
    DB::table('users')->insert([
        'id' => $userId,
        'name' => 'Test User',
        'email' => 'test-' . \Illuminate\Support\Str::random(6) . '@example.com',
        'password' => bcrypt('password'),
        'role' => 'customer',
        'tenant_id' => $tenantId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    echo "   ✓ User created: {$userId}\n\n";

    echo "=== RAW DB TESTS PASSED ===\n";

} catch (\Throwable $e) {
    echo "\n❌ ERROR:\n";
    echo "   " . get_class($e) . "\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "Full message:\n" . substr($e->getMessage(), 0, 500) . "\n";
}
