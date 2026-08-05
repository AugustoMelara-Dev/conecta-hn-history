<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantOnboardingState;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
$mode = (string) ($argv[1] ?? '');
$runId = (string) ($argv[2] ?? '');
$email = $runId.'@conectahn.test';

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if ($mode === 'setup') {
    $user = new User;
    $user->forceFill([
        'name' => 'Owner Onboarding Smoke',
        'email' => $email,
        'password' => Hash::make('ChangeMe123!'),
        'role' => 'seller',
        'email_verified_at' => now(),
    ]);
    User::withoutEvents(fn () => $user->save());

    $tenant = new Tenant;
    $tenant->forceFill(array_filter([
        'id' => (string) Str::uuid(),
        'name' => 'Onboarding Smoke HN',
        'slug' => $runId,
        'user_id' => $user->id,
        'description' => null,
        'whatsapp_number' => null,
        'phone' => null,
        'simple_schedule' => null,
        'is_active' => true,
        'status' => 'active',
        'plan_type' => 'pro',
        'subscription_status' => 'active',
        'is_pro' => true,
        'business_type' => 'retail',
        'commercial_mode' => 'products',
        'product_limit' => 100,
    ], fn ($value, string $column): bool => Schema::hasColumn('tenants', $column), ARRAY_FILTER_USE_BOTH));
    Tenant::withoutEvents(fn () => $tenant->save());

    $user->forceFill(['tenant_id' => $tenant->id])->save();

    echo json_encode([
        'email' => $email,
        'password' => 'ChangeMe123!',
        'slug' => $tenant->slug,
        'tenantId' => $tenant->id,
        'userId' => $user->id,
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

$tenant = Tenant::withoutGlobalScopes()->where('slug', $runId)->firstOrFail();
$user = User::where('email', $email)->firstOrFail();

if ($mode === 'advance') {
    $tenant->forceFill([
        'description' => 'Negocio local con propuesta clara y datos verificados.',
        'whatsapp_number' => '+50499990077',
    ])->save();

    echo json_encode(['advanced' => true], JSON_THROW_ON_ERROR);
    exit(0);
}

if ($mode === 'inspect') {
    $state = TenantOnboardingState::query()
        ->where('tenant_id', (string) $tenant->getKey())
        ->where('user_id', (string) $user->getKey())
        ->first();

    echo json_encode([
        'dismissed' => $state?->first_run_dismissed_at !== null,
        'progress' => (int) ($state?->progress ?? 0),
        'currentStepKey' => $state?->first_run_step_key,
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

fwrite(STDERR, 'Unknown helper mode: '.$mode);
exit(1);
