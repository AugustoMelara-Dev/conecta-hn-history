<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\Growth\Enums\AttributionStage;
use App\Infrastructure\Growth\EloquentProgram13AttributionReader;
use App\Models\City;
use App\Models\Interaction;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DovraProgram13AttributionContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_program13_attribution_reader_separates_prepared_influenced_and_verified_buckets(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => City::factory()->create()->id]);
        $otherTenant = Tenant::factory()->create();

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'prepared-1',
            'session_id' => 'prepared-1',
            'type' => Interaction::TYPE_CHECKOUT_STARTED,
            'signal_class' => 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
        ]);

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'prepared-2',
            'session_id' => 'prepared-2',
            'type' => Interaction::TYPE_UPGRADE_STARTED,
            'signal_class' => 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
        ]);

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'influenced-1',
            'session_id' => 'influenced-1',
            'type' => Interaction::TYPE_MICROSITE_VIEW,
            'signal_class' => 'traffic',
            'source_authority' => 'public_client',
            'is_verified' => false,
            'utm_source' => 'seo',
            'utm_campaign' => 'program13',
        ]);

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'influenced-2',
            'session_id' => 'influenced-2',
            'type' => Interaction::TYPE_CALL_CLICK,
            'signal_class' => 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
            'utm_source' => 'partner',
        ]);

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'ignored-1',
            'session_id' => 'ignored-1',
            'type' => Interaction::TYPE_CART_ADD,
            'signal_class' => 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
        ]);

        $this->insertInteraction([
            'tenant_id' => $otherTenant->getKey(),
            'visitor_id' => 'foreign-influenced',
            'session_id' => 'foreign-influenced',
            'type' => Interaction::TYPE_MICROSITE_VIEW,
            'signal_class' => 'traffic',
            'source_authority' => 'public_client',
            'is_verified' => false,
            'utm_source' => 'seo',
        ]);

        $this->insertOutcome((string) $tenant->getKey(), 'order-1', CommercialOutcome::OrderPaid, 2_500, 'HNL');
        $this->insertOutcome((string) $tenant->getKey(), 'order-1', CommercialOutcome::OrderRefunded, -500, 'HNL');
        $this->insertOutcome((string) $tenant->getKey(), 'appointment-1', CommercialOutcome::AppointmentCompleted, 0, null, 'appointment');
        $this->insertOutcome((string) $otherTenant->getKey(), 'order-9', CommercialOutcome::OrderPaid, 9_900, 'USD');

        $snapshot = (new EloquentProgram13AttributionReader)->snapshot((string) $tenant->getKey());

        self::assertSame(AttributionStage::Prepared, $snapshot->prepared->stage);
        self::assertSame(AttributionStage::Influenced, $snapshot->influenced->stage);
        self::assertSame(AttributionStage::Verified, $snapshot->verified->stage);
        self::assertSame('interactions.type', $snapshot->prepared->evidenceSource);
        self::assertSame('interactions.utm_*', $snapshot->influenced->evidenceSource);
        self::assertSame('verified_commercial_outcomes', $snapshot->verified->evidenceSource);
        self::assertSame(2, $snapshot->bucket(AttributionStage::Prepared)->count);
        self::assertSame(2, $snapshot->bucket(AttributionStage::Influenced)->count);
        self::assertSame(3, $snapshot->bucket(AttributionStage::Verified)->count);
        self::assertSame(['HNL' => 2000], $snapshot->bucket(AttributionStage::Verified)->revenueByCurrencyMinor);
        self::assertSame([], $snapshot->bucket(AttributionStage::Prepared)->revenueByCurrencyMinor);
        self::assertSame([], $snapshot->bucket(AttributionStage::Influenced)->revenueByCurrencyMinor);
        self::assertContains(
            'Prepared and influenced buckets are operational signals, not verified revenue.',
            $snapshot->limitations,
        );
    }

    public function test_unverified_interaction_with_commercial_language_does_not_create_a_verified_outcome(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => City::factory()->create()->id]);

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'unverified-payment',
            'session_id' => 'unverified-payment',
            'type' => Interaction::TYPE_CHECKOUT_PAID,
            'signal_class' => 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
            'utm_source' => 'untrusted-client',
            'metadata' => ['amount_minor' => 999_999, 'currency' => 'USD'],
        ]);

        $snapshot = (new EloquentProgram13AttributionReader)->snapshot((string) $tenant->getKey());

        self::assertSame(0, $snapshot->prepared->count);
        self::assertSame(1, $snapshot->influenced->count);
        self::assertSame(0, $snapshot->verified->count);
        self::assertSame([], $snapshot->verified->revenueByCurrencyMinor);
    }

    public function test_prepared_signal_is_not_influenced_or_verified(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => City::factory()->create()->id]);

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'prepared-only',
            'session_id' => 'prepared-only',
            'type' => Interaction::TYPE_CHECKOUT_STARTED,
            'signal_class' => 'intent',
            'source_authority' => 'public_client',
            'is_verified' => false,
            'utm_source' => 'paid-search',
        ]);

        $snapshot = (new EloquentProgram13AttributionReader)->snapshot((string) $tenant->getKey());

        self::assertSame(1, $snapshot->prepared->count);
        self::assertSame(0, $snapshot->influenced->count);
        self::assertSame(0, $snapshot->verified->count);
    }

    public function test_influenced_signal_is_not_prepared_or_verified(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => City::factory()->create()->id]);

        $this->insertInteraction([
            'tenant_id' => $tenant->getKey(),
            'visitor_id' => 'influenced-only',
            'session_id' => 'influenced-only',
            'type' => Interaction::TYPE_MICROSITE_VIEW,
            'signal_class' => 'traffic',
            'source_authority' => 'public_client',
            'is_verified' => false,
            'utm_source' => 'partner',
        ]);

        $snapshot = (new EloquentProgram13AttributionReader)->snapshot((string) $tenant->getKey());

        self::assertSame(0, $snapshot->prepared->count);
        self::assertSame(1, $snapshot->influenced->count);
        self::assertSame(0, $snapshot->verified->count);
    }

    private function insertOutcome(
        string $tenantId,
        string $aggregateId,
        CommercialOutcome $outcome,
        int $amountMinor,
        ?string $currency,
        string $aggregateType = 'order',
    ): void {
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'outcome' => $outcome->value,
            'projection_key' => "{$tenantId}:{$aggregateType}:{$aggregateId}:{$outcome->value}",
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'source_occurred_at' => now(),
            'source_version' => 1,
            'metadata' => json_encode(['authority' => 'server_aggregate'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertInteraction(array $attributes): void
    {
        $interaction = new Interaction;
        $interaction->forceFill($attributes);
        $interaction->save();
    }
}
