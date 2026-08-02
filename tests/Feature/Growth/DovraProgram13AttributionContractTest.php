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
