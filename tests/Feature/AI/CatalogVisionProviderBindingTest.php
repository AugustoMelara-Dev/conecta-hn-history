<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Catalog\CatalogVisionProviderInterface;
use App\Infrastructure\AI\OpenAiCatalogVisionProvider;
use Tests\TestCase;

final class CatalogVisionProviderBindingTest extends TestCase
{
    public function test_catalog_vision_depends_on_an_infrastructure_provider_binding(): void
    {
        self::assertInstanceOf(
            OpenAiCatalogVisionProvider::class,
            app(CatalogVisionProviderInterface::class),
        );
    }
}
