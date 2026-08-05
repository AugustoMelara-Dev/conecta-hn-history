<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use Tests\TestCase;

final class OpenAiStructuredConfigTest extends TestCase
{
    public function test_text_schemas_are_strict_and_default_model_has_positive_pricing(): void
    {
        $schemas = config('ai.schemas');

        self::assertSame([
            'tenant_catalog.read',
            'tenant_availability.read',
        ], config('ai.capabilities.private_tenant_agent.tools'));

        foreach (['public_discovery.answer.v1', 'private_agent.answer.v1'] as $schemaId) {
            $schema = is_array($schemas) ? ($schemas[$schemaId]['json_schema'] ?? null) : null;

            self::assertIsArray($schema);
            self::assertSame('object', $schema['type'] ?? null);
            self::assertFalse($schema['additionalProperties'] ?? true);
            self::assertNotEmpty($schema['required'] ?? []);
            self::assertSame('.*\S.*', $schema['properties']['answer']['pattern'] ?? null);
            $this->assertDoesNotContainUnsupportedOpenAiKeywords($schema);
        }

        $selection = is_array($schemas) ? ($schemas['private_agent.evidence_selection.v1']['json_schema'] ?? null) : null;
        self::assertIsArray($selection);
        self::assertSame(['evidence'], $selection['required'] ?? null);
        self::assertFalse($selection['additionalProperties'] ?? true);
        self::assertArrayNotHasKey('answer', $selection['properties'] ?? []);
        self::assertSame(['type', 'id'], $selection['properties']['evidence']['items']['required'] ?? null);
        self::assertSame(['product', 'service'], $selection['properties']['evidence']['items']['properties']['type']['enum'] ?? null);
        $this->assertDoesNotContainUnsupportedOpenAiKeywords($selection);

        $public = is_array($schemas) ? $schemas['public_discovery.answer.v1']['json_schema'] : [];
        self::assertSame(1, $public['properties']['evidence']['minItems'] ?? null);
        self::assertSame('.*\S.*', $public['properties']['evidence']['items']['properties']['id']['pattern'] ?? null);
        self::assertSame('.*\S.*', $public['properties']['evidence']['items']['properties']['reason']['pattern'] ?? null);
        $pricing = config('ai.providers.openai.models.gpt-4o-mini');
        self::assertIsArray($pricing);
        self::assertGreaterThan(0, (float) ($pricing['input_usd_per_million'] ?? 0));
        self::assertGreaterThan(0, (float) ($pricing['output_usd_per_million'] ?? 0));
    }

    /** @param array<string, mixed> $schema */
    private function assertDoesNotContainUnsupportedOpenAiKeywords(array $schema): void
    {
        foreach ($schema as $keyword => $value) {
            self::assertNotContains($keyword, ['minLength', 'maxLength']);

            if (is_array($value)) {
                $this->assertDoesNotContainUnsupportedOpenAiKeywords($value);
            }
        }
    }
}
