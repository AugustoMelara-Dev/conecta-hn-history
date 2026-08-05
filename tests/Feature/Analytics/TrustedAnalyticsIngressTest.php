<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\Enums\InteractionType;
use App\Models\Interaction;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrustedAnalyticsIngressTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_track_accepts_only_intent_signals(): void
    {
        $tenant = Tenant::factory()->create();

        $this->postJson('/api/v1/analytics/track', [
            'tenant_id' => $tenant->getKey(),
            'type' => InteractionType::CHECKOUT_STARTED->value,
        ])->assertAccepted();

        foreach ([
            InteractionType::CONVERSION,
            InteractionType::PURCHASE,
            InteractionType::CHECKOUT_PAID,
            InteractionType::UPGRADE_PAID,
            InteractionType::LEAD_CREATED,
        ] as $authoritativeType) {
            $this->postJson('/api/v1/analytics/track', [
                'tenant_id' => $tenant->getKey(),
                'type' => $authoritativeType->value,
                'metadata' => ['value' => 999999, 'currency' => 'USD'],
            ])->assertUnprocessable()->assertJsonValidationErrors('type');
        }

        self::assertSame(1, Interaction::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('interactions', [
            'tenant_id' => $tenant->getKey(),
            'type' => InteractionType::CHECKOUT_STARTED->value,
            'signal_class' => 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
        ]);
    }

    public function test_legacy_conversion_beacon_is_tombstoned_and_cannot_create_revenue(): void
    {
        $tenant = Tenant::factory()->create();

        $this->postJson('/api/v1/analytics/conversion', [
            'tenant_slug' => $tenant->slug,
            'event' => InteractionType::PURCHASE->value,
            'transaction_id' => 'hostile-transaction',
            'value' => 999999.99,
            'currency' => 'USD',
        ])->assertGone()->assertJsonPath('reason', 'server_authoritative_outcomes_only');

        self::assertSame(0, Interaction::query()->withoutGlobalScopes()->count());
    }
}
