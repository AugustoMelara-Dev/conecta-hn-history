<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
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
function tableColumns(string $table): array
{
    if (! Schema::hasTable($table)) {
        return [];
    }

    return Schema::getColumnListing($table);
}

$fixture = [
    'tenant_slug' => 'demo-ai-booking-hn',
    'tenant_name' => 'Demo AI Booking HN',
    'owner_name' => 'Owner Demo AI Booking',
    'owner_email' => 'owner-demo-ai-booking-hn@conectahn.app',
    'owner_password' => getenv('DEMO_SERVICE_OWNER_PASSWORD') !== false
        ? (string) getenv('DEMO_SERVICE_OWNER_PASSWORD')
        : (
            getenv('DEMO_OWNER_PASSWORD') !== false
                ? (string) getenv('DEMO_OWNER_PASSWORD')
                : 'ChangeMe123!'
        ),
    'provider_name' => 'Laura Rivera',
    'provider_email' => 'laura.rivera@conectahn.app',
    'provider_phone' => '+50499991111',
    'service_name' => 'Corte y barba express',
    'service_description' => 'Servicio de corte, barba y grooming express para reservas publicas.',
];

$now = now();
$tenantColumns = tableColumns('tenants');
$providerColumns = tableColumns('service_providers');
$serviceColumns = tableColumns('services');
$openingHourColumns = tableColumns('opening_hours');

DB::transaction(function () use (
    $fixture,
    $tenantColumns,
    $providerColumns,
    $serviceColumns,
    $openingHourColumns,
    $now
): void {
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
        ->whereIn('slug', ['belleza', 'servicios', 'salud'])
        ->orderByRaw("FIELD(slug, 'belleza', 'servicios', 'salud')")
        ->first();

    if ($category === null) {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Servicios',
            'slug' => 'servicios',
            'description' => 'Categoria base para fixture persistente de booking.',
            'is_active' => true,
            'is_featured' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $category = DB::table('categories')->where('id', $categoryId)->first();
    }

    if ($city === null || $category === null) {
        throw new RuntimeException('No se pudo resolver city/category para fixture booking.');
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
            'phone' => $fixture['provider_phone'],
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
        throw new RuntimeException('No se pudo crear/actualizar owner booking fixture.');
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

    if (in_array('status', $tenantColumns, true)) {
        $tenantPayload['status'] = 'approved';
    }
    if (in_array('subscription_status', $tenantColumns, true)) {
        $tenantPayload['subscription_status'] = 'active';
    }
    if (in_array('verification_status', $tenantColumns, true)) {
        $tenantPayload['verification_status'] = 'verified';
    }
    if (in_array('verified_at', $tenantColumns, true)) {
        $tenantPayload['verified_at'] = $now;
    }
    if (in_array('publication_paused_at', $tenantColumns, true)) {
        $tenantPayload['publication_paused_at'] = null;
    }
    if (in_array('plan_type', $tenantColumns, true)) {
        $tenantPayload['plan_type'] = 'pro';
    }
    if (in_array('plan', $tenantColumns, true)) {
        $tenantPayload['plan'] = 'PRO';
    }
    if (in_array('business_type', $tenantColumns, true)) {
        $tenantPayload['business_type'] = 'service';
    }
    if (in_array('business_segment_key', $tenantColumns, true)) {
        $tenantPayload['business_segment_key'] = 'salon_barbershop';
    }
    if (in_array('description', $tenantColumns, true)) {
        $tenantPayload['description'] = 'Fixture persistente para validar booking end-to-end en release.';
    }
    if (in_array('about', $tenantColumns, true)) {
        $tenantPayload['about'] = 'Micrositio de servicios con agenda publica para smoke y pruebas release-critical.';
    }
    if (in_array('slogan', $tenantColumns, true)) {
        $tenantPayload['slogan'] = 'Reserva rapida con confirmacion clara.';
    }
    if (in_array('whatsapp_number', $tenantColumns, true)) {
        $tenantPayload['whatsapp_number'] = $fixture['provider_phone'];
    }
    if (in_array('phone', $tenantColumns, true)) {
        $tenantPayload['phone'] = $fixture['provider_phone'];
    }
    if (in_array('email', $tenantColumns, true)) {
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

    if (Schema::hasTable('tenant_memberships')) {
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
                'next_action_copy' => 'Fixture persistente de booking listo para smoke release.',
                'next_action_href' => '/app',
                'milestones' => json_encode([
                    'profile' => true,
                    'services' => true,
                    'payments' => true,
                    'launch' => true,
                ], JSON_THROW_ON_ERROR),
                'last_completed_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    if (Schema::hasTable('service_providers') && $providerColumns !== []) {
        $provider = DB::table('service_providers')
            ->where('tenant_id', $tenantId)
            ->where('email', $fixture['provider_email'])
            ->first();

        $providerPayload = [
            'tenant_id' => $tenantId,
            'name' => $fixture['provider_name'],
            'email' => $fixture['provider_email'],
            'updated_at' => $now,
        ];

        if (in_array('phone', $providerColumns, true)) {
            $providerPayload['phone'] = $fixture['provider_phone'];
        }
        if (in_array('specialty', $providerColumns, true)) {
            $providerPayload['specialty'] = 'Barberia';
        }
        if (in_array('is_active', $providerColumns, true)) {
            $providerPayload['is_active'] = true;
        }
        if (in_array('schedule_config', $providerColumns, true)) {
            $providerPayload['schedule_config'] = json_encode([
                'monday' => ['09:00-18:00'],
                'tuesday' => ['09:00-18:00'],
                'wednesday' => ['09:00-18:00'],
                'thursday' => ['09:00-18:00'],
                'friday' => ['09:00-18:00'],
                'saturday' => ['09:00-16:00'],
            ], JSON_THROW_ON_ERROR);
        }

        if ($provider === null) {
            $providerPayload['created_at'] = $now;
            DB::table('service_providers')->insert($providerPayload);
        } else {
            DB::table('service_providers')
                ->where('id', $provider->id)
                ->update($providerPayload);
        }
    }

    if (Schema::hasTable('services') && $serviceColumns !== []) {
        $service = DB::table('services')
            ->where('tenant_id', $tenantId)
            ->where('name', $fixture['service_name'])
            ->first();

        $servicePayload = [
            'tenant_id' => $tenantId,
            'name' => $fixture['service_name'],
            'description' => $fixture['service_description'],
            'updated_at' => $now,
        ];

        if (in_array('duration_minutes', $serviceColumns, true)) {
            $servicePayload['duration_minutes'] = 45;
        }
        if (in_array('buffer_before', $serviceColumns, true)) {
            $servicePayload['buffer_before'] = 0;
        }
        if (in_array('buffer_after', $serviceColumns, true)) {
            $servicePayload['buffer_after'] = 0;
        }
        if (in_array('price', $serviceColumns, true)) {
            $servicePayload['price'] = 320.00;
        }
        if (in_array('is_active', $serviceColumns, true)) {
            $servicePayload['is_active'] = true;
        }

        if ($service === null) {
            $servicePayload['created_at'] = $now;
            DB::table('services')->insert($servicePayload);
        } else {
            DB::table('services')
                ->where('id', $service->id)
                ->update($servicePayload);
        }
    }

    if (Schema::hasTable('opening_hours') && $openingHourColumns !== []) {
        foreach (range(0, 6) as $dayOfWeek) {
            $existing = DB::table('opening_hours')
                ->where('tenant_id', $tenantId)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            $openingPayload = [
                'tenant_id' => $tenantId,
                'day_of_week' => $dayOfWeek,
                'opening_time' => '09:00:00',
                'closing_time' => $dayOfWeek === 6 ? '16:00:00' : '18:00:00',
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $openingPayload['created_at'] = $now;
                DB::table('opening_hours')->insert($openingPayload);
            } else {
                DB::table('opening_hours')
                    ->where('id', $existing->id)
                    ->update($openingPayload);
            }
        }
    }
});

$tenant = DB::table('tenants')->where('slug', $fixture['tenant_slug'])->first();
$tenantId = $tenant !== null ? (string) $tenant->id : null;

$serviceCount = 0;
$providerCount = 0;
$openingHoursCount = 0;
if ($tenantId !== null) {
    if (Schema::hasTable('services')) {
        $serviceCount = (int) DB::table('services')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();
    }
    if (Schema::hasTable('service_providers')) {
        $providerCount = (int) DB::table('service_providers')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();
    }
    if (Schema::hasTable('opening_hours')) {
        $openingHoursCount = (int) DB::table('opening_hours')
            ->where('tenant_id', $tenantId)
            ->count();
    }
}

$out = [
    'ok' => true,
    'tenant_slug' => $fixture['tenant_slug'],
    'tenant_id' => $tenantId,
    'owner_email' => $fixture['owner_email'],
    'service_count' => $serviceCount,
    'provider_count' => $providerCount,
    'opening_hours_count' => $openingHoursCount,
    'booking_url' => '/t/'.$fixture['tenant_slug'].'/book',
    'timestamp' => now()->toIso8601String(),
];

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
