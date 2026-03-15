<?php

use App\Domain\Marketing\Enums\AudienceType;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('campaign can store and cast dynamic audience type', function () {
    $tenantId = Str::uuid()->toString();
    $tenant = Tenant::forceCreate([
        'id' => $tenantId,
        'name' => 'Audience Test Tenant',
        'slug' => 'audience-test-'.Str::random(6),
    ]);

    app(TenantContext::class)->setTenant($tenant->id);

    $campaign = Campaign::create([
        'subject' => 'VIP Followers Promo',
        'content' => 'Thanks for following!',
        'type' => 'email',
        'audience_type' => AudienceType::FOLLOWERS,
        'status' => 'draft',
        'recipients_count' => 0,
    ]);

    expect($campaign->audience_type)->toBe(AudienceType::FOLLOWERS)
        ->and($campaign->audience_type->label())->toBe('Seguidores');

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'audience_type' => 'followers',
    ]);

    app(TenantContext::class)->reset();
});
