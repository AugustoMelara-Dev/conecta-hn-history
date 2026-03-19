<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Domain\Marketing\Enums\AudienceType;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_and_casts_the_dynamic_audience_type(): void
    {
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

        self::assertSame(AudienceType::FOLLOWERS, $campaign->audience_type);
        self::assertSame('Seguidores', $campaign->audience_type->label());

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'audience_type' => 'followers',
        ]);

        app(TenantContext::class)->reset();
    }
}
