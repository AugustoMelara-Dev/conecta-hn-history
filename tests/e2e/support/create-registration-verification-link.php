<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

const DOVRA_REGISTRATION_EMAIL = 'dovra.registration.e2e@example.test';
const DOVRA_REGISTRATION_SLUG = 'dovra-registration-e2e';

$registrationScenarioInput = getenv('DOVRA_E2E_REGISTRATION_SCENARIO');
$scenario = $registrationScenarioInput === false
    ? 'products'
    : strtolower(trim($registrationScenarioInput));
$scenarios = [
    'products' => [
        'email' => DOVRA_REGISTRATION_EMAIL,
        'slug' => DOVRA_REGISTRATION_SLUG,
        'business_name' => 'Dovra Registration E2E',
        'owner_name' => 'Dovra Owner E2E',
        'address' => 'Avenida E2E 2026, Tegucigalpa',
        'commercial_mode' => 'products',
        'business_type' => 'retail',
        'rubro_key' => 'retail-core',
    ],
    'appointments' => [
        'email' => 'dovra.appointments.e2e@example.test',
        'slug' => 'dovra-appointments-e2e',
        'business_name' => 'Dovra Appointments E2E',
        'owner_name' => 'Dovra Appointments Owner E2E',
        'address' => 'Avenida Appointments E2E 2026, Tegucigalpa',
        'commercial_mode' => 'appointments',
        'business_type' => 'service',
        'rubro_key' => 'service-core',
    ],
    'hybrid' => [
        'email' => 'dovra.hybrid.e2e@example.test',
        'slug' => 'dovra-hybrid-e2e',
        'business_name' => 'Dovra Hybrid E2E',
        'owner_name' => 'Dovra Hybrid Owner E2E',
        'address' => 'Avenida Hybrid E2E 2026, Tegucigalpa',
        'commercial_mode' => 'hybrid',
        'business_type' => 'service',
        'rubro_key' => 'hybrid-core',
    ],
];

$repositoryRoot = dirname(__DIR__, 3);
$emitFailure = static function (): never {
    fwrite(STDERR, "registration verification link unavailable\n");
    exit(1);
};

if ($argc !== 1) {
    $emitFailure();
}

if (! array_key_exists($scenario, $scenarios)) {
    $emitFailure();
}

$scenarioConfig = $scenarios[$scenario];

// The helper must receive the same signing key as the running local server.
// Capture the process environment before Laravel boots and loads `.env`, so a
// missing explicit key cannot silently fall back to a different local value.
$providedAppKey = getenv('APP_KEY');
if (! is_string($providedAppKey) || trim($providedAppKey) === '') {
    $emitFailure();
}

try {
    require $repositoryRoot.'/vendor/autoload.php';
    $application = require $repositoryRoot.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    if (! $application->environment(['local', 'testing'])) {
        $emitFailure();
    }

    if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_EMAIL_VERIFICATION') !== 'true') {
        $emitFailure();
    }

    if ((string) config('database.default') !== 'sqlite') {
        $emitFailure();
    }

    $configuredDatabase = (string) config('database.connections.sqlite.database');
    $expectedDatabase = (string) getenv('DOVRA_E2E_DATABASE_PATH');
    if ($configuredDatabase === '' || $configuredDatabase === ':memory:' || $expectedDatabase === '' || $expectedDatabase === ':memory:') {
        $emitFailure();
    }

    if (! is_file($configuredDatabase) || ! is_file($expectedDatabase)) {
        $emitFailure();
    }

    if (filesize($configuredDatabase) === 0 || filesize($expectedDatabase) === 0) {
        $emitFailure();
    }

    $configuredRealPath = realpath($configuredDatabase);
    $expectedRealPath = realpath($expectedDatabase);
    if ($configuredRealPath === false || $expectedRealPath === false || $configuredRealPath !== $expectedRealPath) {
        $emitFailure();
    }

    $payload = DB::transaction(static function () use ($scenario, $scenarioConfig): array {
        $users = User::query()
            ->where('email', $scenarioConfig['email'])
            ->lockForUpdate()
            ->get();

        if ($users->count() !== 1) {
            throw new RuntimeException('synthetic owner missing');
        }

        /** @var User $user */
        $user = $users->sole();
        if ($user->email_verified_at !== null) {
            throw new RuntimeException('synthetic owner already verified');
        }

        $tenants = DB::table('tenants')
            ->where('slug', $scenarioConfig['slug'])
            ->lockForUpdate()
            ->get();

        if ($tenants->count() !== 1) {
            throw new RuntimeException('synthetic tenant missing');
        }

        $tenant = $tenants->sole();
        $ownerRole = $user->role instanceof BackedEnum ? $user->role->value : (string) $user->role;
        if (! (bool) $tenant->is_active
            || (string) $tenant->user_id !== (string) $user->id
            || (string) $user->tenant_id !== (string) $tenant->id
            || (string) $user->name !== $scenarioConfig['owner_name']
            || (string) $tenant->name !== $scenarioConfig['business_name']
            || $ownerRole !== 'seller'
            || (string) $tenant->plan_type !== 'semilla'
            || (bool) $tenant->is_pro
            || (string) $tenant->commercial_mode !== $scenarioConfig['commercial_mode']
            || (string) $tenant->business_type !== $scenarioConfig['business_type']
            || (string) $tenant->rubro_key !== $scenarioConfig['rubro_key']) {
            throw new RuntimeException('synthetic owner relationship invalid');
        }

        $primaryMarkets = DB::table('tenant_markets')
            ->where('tenant_id', $tenant->id)
            ->where('is_primary', true)
            ->lockForUpdate()
            ->get();

        if ($primaryMarkets->count() !== 1) {
            throw new RuntimeException('synthetic primary market missing');
        }

        $primaryMarket = $primaryMarkets->sole();
        if ((string) $primaryMarket->country_code !== 'HN'
            || (string) $primaryMarket->locale_code !== 'es-HN'
            || (string) $primaryMarket->currency_code !== 'HNL'
            || (string) $primaryMarket->timezone_name !== 'America/Tegucigalpa') {
            throw new RuntimeException('synthetic primary market invalid');
        }

        $primaryLocations = DB::table('locations')
            ->where('tenant_id', $tenant->id)
            ->where('is_primary', true)
            ->lockForUpdate()
            ->get();

        if ($primaryLocations->count() !== 1) {
            throw new RuntimeException('synthetic primary location missing');
        }

        $primaryLocation = $primaryLocations->sole();
        $locality = DB::table('localities')
            ->where('id', $primaryLocation->locality_id)
            ->where('country_code', 'HN')
            ->where('is_active', true)
            ->first();

        if ($locality === null
            || (string) $primaryLocation->country_code !== 'HN'
            || (string) $primaryLocation->locale_code !== 'es-HN'
            || (string) $primaryLocation->currency_code !== 'HNL'
            || (string) $primaryLocation->timezone_name !== 'America/Tegucigalpa'
            || (string) $primaryLocation->name !== $scenarioConfig['business_name']
            || (string) $primaryLocation->address !== $scenarioConfig['address']) {
            throw new RuntimeException('synthetic primary location invalid');
        }

        $legacyPayments = DB::table('payments')
            ->where('tenant_id', $tenant->id)
            ->lockForUpdate()
            ->count();
        $subscriptionPayments = DB::table('subscription_payments')
            ->where('tenant_id', $tenant->id)
            ->lockForUpdate()
            ->count();

        if ($legacyPayments !== 0 || $subscriptionPayments !== 0) {
            throw new RuntimeException('synthetic registration unexpectedly created payment records');
        }

        $panel = Filament::getPanel('app');
        $verificationUrl = $panel->getVerifyEmailUrl($user);

        return [
            'status' => 'ok',
            'scenario' => $scenario,
            'slug' => $scenarioConfig['slug'],
            'commercial_mode' => $scenarioConfig['commercial_mode'],
            'business_type' => $scenarioConfig['business_type'],
            'rubro_key' => $scenarioConfig['rubro_key'],
            'already_verified' => false,
            'verification_url' => $verificationUrl,
        ];
    });

    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable) {
    $emitFailure();
}
