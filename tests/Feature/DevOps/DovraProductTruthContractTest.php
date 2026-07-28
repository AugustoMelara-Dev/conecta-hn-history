<?php

declare(strict_types=1);

namespace Tests\Feature\DevOps;

use Tests\TestCase;

final class DovraProductTruthContractTest extends TestCase
{
    public function test_canonical_product_identity_is_configured_without_unsupported_clearance_claims(): void
    {
        self::assertSame('Dovra AI', config('dovra.identity.name'));
        self::assertSame('Dovra', config('dovra.identity.short_name'));
        self::assertSame('Global Conversational Business OS', config('dovra.identity.category'));
        self::assertSame(
            'Dovra deploys AI employees that know a business, work through its channels, and turn conversations into measurable results.',
            config('dovra.identity.commercial_promise'),
        );
        self::assertSame('HN', config('dovra.identity.origin_market'));
        self::assertNull(config('dovra.legal.entity_name'));
        self::assertFalse(config('dovra.legal.name_clearance_confirmed'));
    }

    public function test_application_defaults_and_environment_example_use_dovra_product_identity(): void
    {
        $appConfig = (string) file_get_contents(config_path('app.php'));
        $environmentExample = (string) file_get_contents(base_path('.env.example'));

        self::assertStringContainsString("env('APP_NAME', 'Dovra AI')", $appConfig);
        self::assertStringContainsString('APP_NAME="Dovra AI"', $environmentExample);
        self::assertStringContainsString('DOVRA_LEGAL_ENTITY_NAME=', $environmentExample);
        self::assertStringContainsString('DOVRA_NAME_CLEARANCE_CONFIRMED=false', $environmentExample);
        self::assertStringNotContainsString('DOVRA_NAME_CLEARANCE_CONFIRMED=true', $environmentExample);
    }
}
