<?php

use App\Infrastructure\Billing\Persistence\ConfigPlanRepository;
use App\Settings\BaseSettings;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

test('native general settings abstraction loads from database', function () {
    DB::table('settings')->updateOrInsert(
        ['group' => 'general', 'name' => 'pro_plan_price'],
        [
            'payload' => json_encode(1499.99),
            'locked' => false,
            'updated_at' => now(),
        ]
    );

    Cache::forget('settings.general');
    BaseSettings::clearRuntimeCache();

    $settings = new GeneralSettings;

    expect($settings->pro_plan_price)->toBe(1499.99);
});

test('billing plan repository uses admin configured plan prices', function () {
    DB::table('settings')->updateOrInsert(
        ['group' => 'general', 'name' => 'pro_plan_price'],
        [
            'payload' => json_encode(649.99),
            'locked' => false,
            'updated_at' => now(),
        ]
    );

    DB::table('settings')->updateOrInsert(
        ['group' => 'general', 'name' => 'free_plan_price'],
        [
            'payload' => json_encode(0.00),
            'locked' => false,
            'updated_at' => now(),
        ]
    );

    Cache::forget('settings.general');
    BaseSettings::clearRuntimeCache();

    $plan = (new ConfigPlanRepository)->findByIdentifier('pro');

    expect($plan)->not()->toBeNull()
        ->and($plan->price->amount)->toBe(64999)
        ->and($plan->price->currency)->toBe('HNL');
});
