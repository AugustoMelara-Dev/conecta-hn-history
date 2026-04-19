<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DirectoryGuideOpsReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_outputs_quality_and_cost_summary_in_json_mode(): void
    {
        $user = User::factory()->create();

        DB::table('directory_guide_request_metrics')->insert([
            [
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $user->getKey(),
                'status_code' => 200,
                'ranking_mode' => 'hybrid',
                'reasoning_basis' => 'sql_filter_plus_embedding_rerank',
                'degraded_reason' => null,
                'degraded_to_search' => false,
                'candidate_count' => 3,
                'tenant_count' => 2,
                'latency_ms' => 120,
                'quota_limit' => 5,
                'quota_used' => 1,
                'quota_remaining' => 4,
                'quota_reset_at' => now()->addHours(5),
                'budget' => 100,
                'city' => 'tegucigalpa',
                'category' => 'restaurantes',
                'search' => 'cafe',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $user->getKey(),
                'status_code' => 200,
                'ranking_mode' => 'sql',
                'reasoning_basis' => 'sql_budget_filter',
                'degraded_reason' => 'openai_timeout',
                'degraded_to_search' => false,
                'candidate_count' => 2,
                'tenant_count' => 1,
                'latency_ms' => 450,
                'quota_limit' => 5,
                'quota_used' => 2,
                'quota_remaining' => 3,
                'quota_reset_at' => now()->addHours(5),
                'budget' => 200,
                'city' => 'tegucigalpa',
                'category' => 'restaurantes',
                'search' => 'almuerzo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $user->getKey(),
                'status_code' => 429,
                'ranking_mode' => null,
                'reasoning_basis' => 'rate_limiter_directory_guide_daily',
                'degraded_reason' => 'quota_exceeded',
                'degraded_to_search' => true,
                'candidate_count' => 0,
                'tenant_count' => 0,
                'latency_ms' => 60000,
                'quota_limit' => 5,
                'quota_used' => 5,
                'quota_remaining' => 0,
                'quota_reset_at' => now()->addHours(5),
                'budget' => 200,
                'city' => 'tegucigalpa',
                'category' => 'restaurantes',
                'search' => 'cafe',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('ai_usage_logs')->insert([
            'user_id' => $user->getKey(),
            'tenant_id' => null,
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'input_tokens' => 100,
            'output_tokens' => 0,
            'cost' => 0.012345,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('directory:guide:ops-report', [
            '--hours' => 24,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"total_requests": 3', $output);
        $this->assertStringContainsString('"status_429": 1', $output);
        $this->assertStringContainsString('"hybrid": 1', $output);
        $this->assertStringContainsString('"openai_timeout": 1', $output);
        $this->assertStringContainsString('"openai_cost_total": 0.012345', $output);
    }

    public function test_it_fails_when_fail_on_alert_is_enabled_and_threshold_is_exceeded(): void
    {
        $user = User::factory()->create();

        config([
            'directory.guide.alerts.openai_auth_failed_count' => 1,
        ]);

        DB::table('directory_guide_request_metrics')->insert([
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->getKey(),
            'status_code' => 200,
            'ranking_mode' => 'sql',
            'reasoning_basis' => 'sql_budget_filter',
            'degraded_reason' => 'openai_auth_failed',
            'degraded_to_search' => false,
            'candidate_count' => 1,
            'tenant_count' => 1,
            'latency_ms' => 220,
            'quota_limit' => 5,
            'quota_used' => 1,
            'quota_remaining' => 4,
            'quota_reset_at' => now()->addHours(4),
            'budget' => 100,
            'city' => 'tegucigalpa',
            'category' => 'restaurantes',
            'search' => 'cafe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('directory:guide:ops-report', [
            '--hours' => 24,
            '--json' => true,
            '--fail-on-alert' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('openai_auth_failed_count_exceeded', $output);
    }
}
