<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Infrastructure\Tenant\Transformers\TenantMicrositeTransformer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantMicrositeTransformerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_the_about_field_for_storytelling(): void
    {
        $tenant = Tenant::factory()->create([
            'about' => 'Our story began in 2020...',
            'description' => 'Test business',
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertArrayHasKey('about', $result);
        self::assertSame('Our story began in 2020...', $result['about']);
    }

    #[Test]
    public function it_exposes_a_structured_location_object_with_typed_coordinates(): void
    {
        $tenant = Tenant::factory()->create([
            'latitude' => 15.4994,
            'longitude' => -88.0250,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertArrayHasKey('location', $result);
        self::assertIsArray($result['location']);
        self::assertSame(15.4994, $result['location']['lat']);
        self::assertSame(-88.025, $result['location']['lng']);
        self::assertSame('Honduras', $result['location']['address']);
    }

    #[Test]
    public function it_preserves_legacy_flat_latitude_and_longitude_fields(): void
    {
        $tenant = Tenant::factory()->create([
            'latitude' => 15.4994,
            'longitude' => -88.0250,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertArrayHasKey('latitude', $result);
        self::assertArrayHasKey('longitude', $result);
        self::assertSame(15.4994, $result['latitude']);
        self::assertSame(-88.025, $result['longitude']);
    }

    #[Test]
    public function it_handles_null_coordinates_gracefully(): void
    {
        $tenant = Tenant::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->actingAsTenant((string) $tenant->id);

        $transformer = app(TenantMicrositeTransformer::class);
        $result = $transformer->transform($tenant);

        self::assertNull($result['location']['lat']);
        self::assertNull($result['location']['lng']);
    }
}
