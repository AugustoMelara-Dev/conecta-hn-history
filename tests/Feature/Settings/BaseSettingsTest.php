<?php

use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('native general settings abstraction loads from database', function () {
    DB::table('settings')->insert([
        'group' => 'general',
        'name' => 'pro_plan_price',
        'payload' => json_encode(1499.99),
        'locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $settings = new GeneralSettings();

    expect($settings->pro_plan_price)->toBe(1499.99);
});
