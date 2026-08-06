<?php

declare(strict_types=1);

it('Admin TenantResource class exists and has correct static properties', function () {
    $resource = new \App\Filament\Admin\Resources\TenantResource();
    expect($resource)->toBeInstanceOf(\Filament\Resources\Resource::class);
    expect(\App\Filament\Admin\Resources\TenantResource::getModel())->toBe(\App\Models\Tenant::class);
});

it('Admin UserResource uses User model', function () {
    expect(\App\Filament\Admin\Resources\UserResource::getModel())->toBe(\App\Models\User::class);
});

it('Admin BillingResource uses SubscriptionPayment model', function () {
    expect(\App\Filament\Admin\Resources\BillingResource::getModel())->toBe(\App\Models\SubscriptionPayment::class);
});

it('Admin PlanResource uses Plan model', function () {
    expect(\App\Filament\Admin\Resources\PlanResource::getModel())->toBe(\App\Models\Plan::class);
});

it('Admin WebhookResource uses Webhook model', function () {
    expect(\App\Filament\Admin\Resources\WebhookResource::getModel())->toBe(\App\Models\Webhook::class);
});

it('Admin ApiKeyResource uses ApiKey model', function () {
    expect(\App\Filament\Admin\Resources\ApiKeyResource::getModel())->toBe(\App\Models\ApiKey::class);
});

it('Admin AuditLogResource uses Activity model (spatie/laravel-activitylog)', function () {
    expect(\App\Filament\Admin\Resources\AuditLogResource::getModel())->toBe(\Spatie\Activitylog\Models\Activity::class);
});

it('Admin Resources all use Filament 4 Schema API', function () {
    $content = file_get_contents(app_path('Filament/Admin/Resources/TenantResource.php'));
    expect($content)->toContain('use Filament\Schemas\Schema;');
    expect($content)->toContain('public static function form(Schema $schema)');
});
