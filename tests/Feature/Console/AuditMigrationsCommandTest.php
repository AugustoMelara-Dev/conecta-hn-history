<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuditMigrationsCommandTest extends TestCase
{
    #[Test]
    public function it_reports_duplicate_timestamps_in_json_mode(): void
    {
        $exitCode = Artisan::call('db:audit-migrations', ['--json' => true]);

        $output = Artisan::output();

        $this->assertContains($exitCode, [0, 1]);
        $this->assertStringContainsString('"total_migrations"', $output);
        $this->assertStringContainsString('"schema_governance"', $output);
        $this->assertStringContainsString('2026_01_20_000009_create_outbox_sequences_table.php', $output);
        $this->assertStringContainsString('2026_01_20_000009_optimize_outbox_indexes.php', $output);
    }
}
