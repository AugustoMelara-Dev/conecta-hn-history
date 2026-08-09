<?php

declare(strict_types=1);

use App\Domain\Operations\Events\BusinessOperationChanged;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const DOVRA_BOOKING_CONVERSION_SLUG = 'dovra-booking-conversion-e2e';
const DOVRA_BOOKING_CONVERSION_OWNER = 'dovra.booking.conversion.e2e@example.test';
const DOVRA_BOOKING_SERVICE = 'Servicio Booking E2E';
const DOVRA_BOOKING_PROVIDER = 'Provider Booking E2E';
const DOVRA_BOOKING_CUSTOMER = 'Booking E2E';
const DOVRA_BOOKING_CUSTOMER_EMAIL = 'booking.customer.e2e@example.test';
const DOVRA_BOOKING_CUSTOMER_PHONE = '9999-2027';

$repositoryRoot = dirname(__DIR__, 3);
$fail = static function (string $message): never {
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
};

if ($argc !== 2) {
    $fail('booking conversion inspector requires exactly one appointment id argument');
}

$appointmentId = filter_var($argv[1], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($appointmentId === false) {
    $fail('booking conversion inspector requires a positive appointment id');
}

$providedAppKey = getenv('APP_KEY');
if (! is_string($providedAppKey) || trim($providedAppKey) === '') {
    $fail('booking conversion inspector requires an explicit APP_KEY');
}

try {
    require $repositoryRoot.'/vendor/autoload.php';
    $application = require $repositoryRoot.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    if (! $application->environment(['local', 'testing'])) {
        $fail('booking conversion inspector is restricted to local/testing');
    }

    if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_BOOKING_CONVERSION') !== 'true') {
        $fail('synthetic booking conversion guard is missing');
    }

    if ((string) config('database.default') !== 'sqlite') {
        $fail('booking conversion inspector requires sqlite');
    }

    $configuredDatabase = (string) config('database.connections.sqlite.database');
    $expectedDatabase = (string) getenv('DOVRA_E2E_DATABASE_PATH');
    if ($configuredDatabase === '' || $configuredDatabase === ':memory:'
        || $expectedDatabase === '' || $expectedDatabase === ':memory:') {
        $fail('booking conversion inspector requires a file-backed disposable database');
    }

    $configuredRealPath = realpath($configuredDatabase);
    $expectedRealPath = realpath($expectedDatabase);
    if ($configuredRealPath === false || $expectedRealPath === false
        || $configuredRealPath !== $expectedRealPath
        || ! is_file($configuredRealPath)
        || filesize($configuredRealPath) < 1) {
        $fail('booking conversion inspector database path mismatch');
    }

    foreach ([
        'tenants',
        'appointments',
        'services',
        'service_providers',
        'locations',
        'service_locations',
        'service_provider_locations',
        'availability_rules',
        'outbox_messages',
    ] as $table) {
        if (! Schema::hasTable($table)) {
            $fail("{$table} canonical booking evidence table is missing");
        }
    }

    $tenant = DB::table('tenants')->where('slug', DOVRA_BOOKING_CONVERSION_SLUG)->first();
    if ($tenant === null) {
        $fail('synthetic booking tenant missing');
    }

    $tenantId = (string) $tenant->id;
    if ((bool) $tenant->is_active !== true
        || (Schema::hasColumn('tenants', 'publication_paused_at') && $tenant->publication_paused_at !== null)
        || (Schema::hasColumn('tenants', 'plan_type') && (string) $tenant->plan_type !== 'pro')
        || (Schema::hasColumn('tenants', 'is_pro') && (bool) $tenant->is_pro !== true)
        || (Schema::hasColumn('tenants', 'business_type') && (string) $tenant->business_type !== 'service')
        || (Schema::hasColumn('tenants', 'commercial_mode') && (string) $tenant->commercial_mode !== 'appointments')
        || (Schema::hasColumn('tenants', 'rubro_key') && (string) $tenant->rubro_key !== 'service-booking')
        || (Schema::hasColumn('tenants', 'phone') && $tenant->phone !== null)
        || (Schema::hasColumn('tenants', 'whatsapp_number') && $tenant->whatsapp_number !== null)) {
        $fail('synthetic booking tenant truth boundary is invalid');
    }

    $services = DB::table('services')
        ->where('tenant_id', $tenantId)
        ->where('name', DOVRA_BOOKING_SERVICE)
        ->get();
    if ($services->count() !== 1) {
        $fail('synthetic booking service count is not exactly one');
    }
    $service = $services->sole();
    if ((int) $service->duration_minutes !== 30
        || (float) $service->price !== 350.00
        || (Schema::hasColumn('services', 'price_minor') && (int) $service->price_minor !== 35000)
        || (Schema::hasColumn('services', 'currency') && (string) $service->currency !== 'HNL')
        || (bool) $service->is_active !== true) {
        $fail('synthetic booking service snapshot is invalid');
    }

    $providers = DB::table('service_providers')
        ->where('tenant_id', $tenantId)
        ->where('name', DOVRA_BOOKING_PROVIDER)
        ->get();
    if ($providers->count() !== 1) {
        $fail('synthetic booking provider count is not exactly one');
    }
    $provider = $providers->sole();
    if ((bool) $provider->is_active !== true) {
        $fail('synthetic booking provider is not active');
    }

    $appointments = DB::table('appointments')->where('tenant_id', $tenantId)->get();
    if ($appointments->count() !== 1) {
        $fail('synthetic booking appointment count is not exactly one');
    }
    $appointment = $appointments->sole();
    if ((int) $appointment->id !== (int) $appointmentId) {
        $fail('browser appointment id does not match the sole synthetic appointment');
    }

    $location = DB::table('locations')
        ->where('id', $appointment->location_id)
        ->where('tenant_id', $tenantId)
        ->first();
    if ($location === null) {
        $fail('synthetic booking location is missing or foreign');
    }

    $localStart = CarbonImmutable::parse((string) $appointment->scheduled_at, 'UTC')
        ->setTimezone((string) $appointment->timezone);
    $persistedLocalDateRaw = $appointment->local_date;
    if (! is_string($persistedLocalDateRaw)
        || trim($persistedLocalDateRaw) === ''
        || $persistedLocalDateRaw !== trim($persistedLocalDateRaw)) {
        $fail('synthetic booking local_date snapshot is empty or has non-canonical whitespace');
    }
    $persistedLocalDate = null;
    foreach ([
        ['!Y-m-d', 'Y-m-d'],
        ['!Y-m-d H:i:s', 'Y-m-d H:i:s'],
    ] as [$parseFormat, $roundTripFormat]) {
        try {
            $candidate = CarbonImmutable::createFromFormat(
                $parseFormat,
                $persistedLocalDateRaw,
                (string) $appointment->timezone,
            );
            $errors = CarbonImmutable::getLastErrors();
        } catch (Throwable) {
            continue;
        }
        $hasParseErrors = is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if ($candidate instanceof CarbonImmutable
            && ! $hasParseErrors
            && $candidate->format($roundTripFormat) === $persistedLocalDateRaw) {
            $persistedLocalDate = $candidate->format('Y-m-d');
            break;
        }
    }
    if (! is_string($persistedLocalDate)) {
        $fail('synthetic booking local_date snapshot is not a strict canonical date');
    }
    if ((int) $appointment->service_id !== (int) $service->id
        || (int) $appointment->service_provider_id !== (int) $provider->id
        || (string) $appointment->location_id !== (string) $location->id
        || (string) $appointment->status !== 'pending'
        || (string) $appointment->source !== 'public_booking'
        || (Schema::hasColumn('appointments', 'currency') && (string) $appointment->currency !== 'HNL')
        || (Schema::hasColumn('appointments', 'price_minor') && (int) $appointment->price_minor !== 35000)
        || (Schema::hasColumn('appointments', 'service_name_snapshot') && (string) $appointment->service_name_snapshot !== DOVRA_BOOKING_SERVICE)
        || (Schema::hasColumn('appointments', 'duration_minutes') && (int) $appointment->duration_minutes !== 30)
        || (string) $appointment->timezone !== 'America/Tegucigalpa'
        || $persistedLocalDate !== $localStart->format('Y-m-d')
        || (string) $appointment->customer_name !== DOVRA_BOOKING_CUSTOMER
        || (string) $appointment->customer_email !== DOVRA_BOOKING_CUSTOMER_EMAIL
        || (string) $appointment->customer_phone !== DOVRA_BOOKING_CUSTOMER_PHONE
        || (string) $location->country_code !== 'HN'
        || (string) $location->currency_code !== 'HNL'
        || (string) $location->timezone_name !== 'America/Tegucigalpa'
        || (bool) $location->is_active !== true
        || (bool) $location->is_primary !== true) {
        $fail('synthetic booking appointment snapshot is invalid');
    }

    if (DB::table('service_locations')
        ->where('tenant_id', $tenantId)
        ->where('service_id', $service->id)
        ->where('location_id', $location->id)
        ->count() !== 1
        || DB::table('service_provider_locations')
            ->where('tenant_id', $tenantId)
            ->where('service_provider_id', $provider->id)
            ->where('location_id', $location->id)
            ->count() !== 1) {
        $fail('synthetic booking canonical catalog pivots are invalid');
    }

    $canonicalRules = DB::table('availability_rules')
        ->where('tenant_id', $tenantId)
        ->where('location_id', $location->id)
        ->where('service_id', $service->id)
        ->where('service_provider_id', $provider->id)
        ->where('source', 'canonical')
        ->where('is_active', true)
        ->get();
    if ($canonicalRules->count() < 7) {
        $fail('synthetic booking canonical availability rules are incomplete');
    }

    $outboxMatches = [];
    foreach (DB::table('outbox_messages')
        ->where('type', BusinessOperationChanged::class)
        ->where('aggregate_id', 'appointment:'.$appointment->id)
        ->get() as $message) {
        $payload = is_array($message->payload)
            ? $message->payload
            : json_decode((string) $message->payload, true, 512, JSON_THROW_ON_ERROR);
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventName = $data['eventName'] ?? $data['event_name'] ?? null;
        $payloadTenant = $meta['tenant_id'] ?? $data['tenantId'] ?? $data['tenant_id'] ?? null;
        if ((string) $payloadTenant === $tenantId
            && (string) ($meta['aggregate_id'] ?? $data['aggregateId'] ?? '') === 'appointment:'.$appointment->id
            && (string) $eventName === 'reservation.created') {
            $outboxMatches[] = [
                'type' => (string) $message->type,
                'aggregate_id' => (string) $message->aggregate_id,
                'event_name' => (string) $eventName,
            ];
        }
    }
    if (count($outboxMatches) !== 1) {
        $fail('synthetic booking reservation.created outbox evidence is invalid');
    }

    $foreignAppointments = (int) DB::table('appointments')
        ->where('tenant_id', '<>', $tenantId)
        ->count();
    if ($foreignAppointments !== 0) {
        $fail('synthetic booking fixture has foreign appointments');
    }

    $zeroEvidenceTables = [
        'orders',
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
        'ai_credit_reservations',
        'ai_credit_ledger_entries',
        'ai_credit_usage_alerts',
        'ai_credit_grants',
    ];
    $zeroCounts = [];
    foreach ($zeroEvidenceTables as $table) {
        if (! Schema::hasTable($table)) {
            $fail("{$table} canonical tenant-attributable negative table is missing");
        }
        if (! Schema::hasColumn($table, 'tenant_id')) {
            $fail("{$table} canonical tenant-attributable negative table lacks tenant_id");
        }
        $zeroCounts[$table] = (int) DB::table($table)->where('tenant_id', $tenantId)->count();
    }
    if (array_sum($zeroCounts) !== 0) {
        $fail('synthetic booking fixture unexpectedly has billing/payment/order rows');
    }

    echo json_encode([
        'status' => 'ok',
        'tenant_slug' => DOVRA_BOOKING_CONVERSION_SLUG,
        'tenant_id' => $tenantId,
        'owner_email' => DOVRA_BOOKING_CONVERSION_OWNER,
        'appointment' => [
            'id' => (int) $appointment->id,
            'service_id' => (int) $appointment->service_id,
            'service_name' => DOVRA_BOOKING_SERVICE,
            'provider_id' => (int) $appointment->service_provider_id,
            'location_id' => (string) $appointment->location_id,
            'status' => (string) $appointment->status,
            'source' => (string) $appointment->source,
            'currency' => (string) ($appointment->currency ?? 'HNL'),
            'price_minor' => (int) ($appointment->price_minor ?? 35000),
            'timezone' => (string) $appointment->timezone,
            'local_date' => $persistedLocalDate,
            'scheduled_at_utc' => (string) $appointment->scheduled_at,
            'customer_name' => (string) $appointment->customer_name,
            'customer_email' => (string) $appointment->customer_email,
            'customer_phone' => (string) $appointment->customer_phone,
            'local_display_date' => $localStart->format('l, d M Y'),
            'local_time' => $localStart->format('h:i A'),
        ],
        'availability_rules' => $canonicalRules->count(),
        'outbox' => [
            'reservation_created' => count($outboxMatches),
            'event_name' => $outboxMatches[0]['event_name'],
        ],
        'billing_counts' => $zeroCounts,
        'foreign_appointments' => $foreignAppointments,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
