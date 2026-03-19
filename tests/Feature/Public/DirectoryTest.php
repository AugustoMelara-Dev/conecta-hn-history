<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function loads_the_directory_without_missing_attribute_exceptions(): void
    {
        Tenant::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->get('/directorio');

        $response->assertStatus(200);
    }

    #[Test]
    public function loads_explore_without_missing_attribute_exceptions(): void
    {
        Tenant::factory()->create([
            'is_active' => true,
            'logo_path' => 'logos/test.png',
        ]);

        $response = $this->get('/explore');

        $response->assertStatus(200);
    }
}
