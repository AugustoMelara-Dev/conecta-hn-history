<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Tenant\Commands\EnhanceTenantProfileCommand;
use App\Application\Tenant\Handlers\EnhanceTenantProfileHandler;
use App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob;
use Tests\TestCase;

class AiEnrichmentIntegrationTest extends TestCase
{
    public function test_job_has_expected_queue_contract(): void
    {
        $command = new EnhanceTenantProfileCommand(42, 'Desc', ['retail']);
        $job = new EnhanceTenantProfileJob($command);

        $this->assertSame('enhance_42', $job->uniqueId());
        $this->assertSame(3, $job->tries);
        $this->assertSame(3600, $job->uniqueFor);

        $tags = $job->tags();
        $this->assertContains('ai', $tags);
        $this->assertContains('enhance', $tags);
        $this->assertContains('tenant:42', $tags);
    }

    public function test_job_handle_signature_uses_current_handler_contract(): void
    {
        $method = new \ReflectionMethod(EnhanceTenantProfileJob::class, 'handle');
        $param = $method->getParameters()[0];

        $this->assertSame(EnhanceTenantProfileHandler::class, $param->getType()?->getName());
    }
}
