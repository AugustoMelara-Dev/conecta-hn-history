<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CommercialNarrativeGuardTest extends TestCase
{
    #[Test]
    public function public_marketing_copy_avoids_phantom_or_unverified_claims(): void
    {
        $welcome = file_get_contents(resource_path('js/Pages/Welcome.jsx'));
        $prTemplate = file_get_contents(base_path('.github/pull_request_template.md'));

        self::assertIsString($welcome);
        self::assertIsString($prTemplate);

        self::assertStringContainsString('micrositio publico', $welcome);
        self::assertStringContainsString('billing y cobros', mb_strtolower($welcome));
        self::assertStringNotContainsString('landing builder', mb_strtolower($welcome));
        self::assertStringNotContainsString('99%', $welcome);
        self::assertStringNotContainsString('24/7', $welcome);
        self::assertStringNotContainsString('facturacion fiscal integrada', mb_strtolower($welcome));

        self::assertStringContainsString('Startup Release Checklist', $prTemplate);
        self::assertStringContainsString('Commercial copy matches shipped product today', $prTemplate);
        self::assertStringContainsString('No reopens legacy writes or new active flows under `/workspace`', $prTemplate);
    }
}
