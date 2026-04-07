<?php

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
    $property = new ReflectionProperty(BaseSettings::class, 'runtimeCache');
    $property->setValue([]);

    $settings = new GeneralSettings;

    expect($settings->pro_plan_price)->toBe(1499.99);
});
