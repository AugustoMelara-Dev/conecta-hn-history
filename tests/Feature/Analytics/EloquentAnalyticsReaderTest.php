<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Infrastructure\Analytics\EloquentAnalyticsReader;
use App\Models\Tenant;
use App\Models\TenantAnalytic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class EloquentAnalyticsReaderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reader_maps_legacy_tenant_analytics_columns_to_canonical_contract(): void
    {
        $tenant = Tenant::factory()->create();

        TenantAnalytic::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'date' => '2026-03-01',
            'views' => 28,
            'unique_visitors' => 14,
            'whatsapp_clicks' => 3,
        ]);

        TenantAnalytic::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'date' => '2026-03-02',
            'views' => 42,
            'unique_visitors' => 18,
            'whatsapp_clicks' => 5,
        ]);

        $reader = app(EloquentAnalyticsReader::class);

        $result = $reader->getAnalyticsData(
            (string) $tenant->getKey(),
            new \DateTimeImmutable('2026-03-01 00:00:00'),
            new \DateTimeImmutable('2026-03-02 23:59:59'),
        );

        $this->assertSame([
            ['date' => '2026-03-01', 'visits' => 14, 'total' => 28],
            ['date' => '2026-03-02', 'visits' => 18, 'total' => 42],
        ], $result);
    }

    public function test_model_aliases_keep_total_and_unique_visit_fields_backward_compatible(): void
    {
        $analytic = new TenantAnalytic;
        $analytic->fill([
            'total_visits' => 31,
            'unique_visits' => 12,
        ]);

        $this->assertSame(31, $analytic->getAttributes()['views']);
        $this->assertSame(12, $analytic->getAttributes()['unique_visitors']);
        $this->assertSame(31, $analytic->total_visits);
        $this->assertSame(12, $analytic->unique_visits);
    }
}
