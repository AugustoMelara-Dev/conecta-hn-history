<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProcessOutboxCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_executes_the_outbox_processor_successfully_under_tenant_context(): void
    {
        $this->artisan('outbox:process')
            ->assertExitCode(0);
    }
}
