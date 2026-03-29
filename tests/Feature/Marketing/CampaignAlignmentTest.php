<?php

use App\Application\Shared\DTOs\Mail\CampaignMailData;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

test('campaign schema model and dto are aligned', function () {
    $tenantId = Str::uuid()->toString();
    $tenant = Tenant::forceCreate([
        'id' => $tenantId,
        'name' => 'Marketing Test Tenant',
        'slug' => 'marketing-test-'.Str::random(6),
    ]);

    app(TenantContext::class)->setTenant($tenant->id);

    $campaign = Campaign::create([
        'subject' => 'Black Friday Promo',
        'content' => 'Get 20% off all bookings!',
        'type' => 'email',
        'status' => 'draft',
        'recipients_count' => 0,
    ]);

    expect($campaign->subject)->toBe('Black Friday Promo')
        ->and($campaign->content)->toBe('Get 20% off all bookings!')
        ->and($campaign->status)->toBe('draft');

    $dto = CampaignMailData::fromCampaign($campaign);

    expect($dto->subject)->toBe('Black Friday Promo')
        ->and($dto->body)->toBe('Get 20% off all bookings!')
        ->and($dto->tenantName)->toBe('Marketing Test Tenant');

    app(TenantContext::class)->reset();
});
