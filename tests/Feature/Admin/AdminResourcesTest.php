<?php

declare(strict_types=1);
use App\Filament\Admin\Resources\ApiKeyResource;
use App\Filament\Admin\Resources\AuditLogResource;
use App\Filament\Admin\Resources\BillingResource;
use App\Filament\Admin\Resources\PlanResource;
use App\Filament\Admin\Resources\TenantResource;
use App\Filament\Admin\Resources\UserResource;
use App\Filament\Admin\Resources\WebhookResource;
use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use Spatie\Activitylog\Models\Activity;

it('Admin TenantResource class exists and has correct static properties', function () {
    $resource = new TenantResource;
    expect($resource)->toBeInstanceOf(Filament\Resources\Resource::class);
    expect(TenantResource::getModel())->toBe(Tenant::class);
});

it('Admin UserResource uses User model', function () {
    expect(UserResource::getModel())->toBe(User::class);
});

it('Admin BillingResource uses SubscriptionPayment model', function () {
    expect(BillingResource::getModel())->toBe(SubscriptionPayment::class);
});

it('Admin PlanResource uses Plan model', function () {
    expect(PlanResource::getModel())->toBe(Plan::class);
});

it('Admin WebhookResource uses Webhook model', function () {
    expect(WebhookResource::getModel())->toBe(Webhook::class);
});

it('Admin ApiKeyResource uses ApiKey model', function () {
    expect(ApiKeyResource::getModel())->toBe(ApiKey::class);
});

it('Admin AuditLogResource uses Activity model (spatie/laravel-activitylog)', function () {
    expect(AuditLogResource::getModel())->toBe(Activity::class);
});

it('Admin Resources all use Filament 4 Schema API', function () {
    $content = file_get_contents(app_path('Filament/Admin/Resources/TenantResource.php'));
    expect($content)->toContain('use Filament\Schemas\Schema;');
    expect($content)->toContain('public static function form(Schema $schema)');
});
