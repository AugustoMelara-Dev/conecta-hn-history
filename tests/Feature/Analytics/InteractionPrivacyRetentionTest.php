<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Models\Interaction;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InteractionPrivacyRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_interactions_expire_under_the_configured_retention_policy(): void
    {
        config()->set('analytics.interaction_retention_days', 30);
        $tenant = Tenant::factory()->create();
        DB::table('interactions')->insert([
            [
                'tenant_id' => $tenant->getKey(),
                'type' => 'whatsapp_click',
                'ip_address' => '203.0.113.10',
                'user_agent' => 'private-agent',
                'created_at' => now()->subDays(31),
                'updated_at' => now()->subDays(31),
            ],
            [
                'tenant_id' => $tenant->getKey(),
                'type' => 'call',
                'ip_address' => '203.0.113.11',
                'user_agent' => 'current-agent',
                'created_at' => now()->subDays(29),
                'updated_at' => now()->subDays(29),
            ],
        ]);

        self::assertSame(1, (new Interaction)->prunable()->count());
    }

    public function test_interaction_error_handler_has_a_source_ratchet_against_raw_payload_logging(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/V1/InteractionController.php'));

        self::assertIsString($source);
        self::assertStringNotContainsString("'payload' => \$request->all()", $source);
        self::assertStringNotContainsString("'error' => \$e->getMessage()", $source);
    }
}
