<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    #[Test]
    public function example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
