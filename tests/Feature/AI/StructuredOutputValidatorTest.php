<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Infrastructure\AI\LaravelStructuredOutputValidator;
use Tests\TestCase;

final class StructuredOutputValidatorTest extends TestCase
{
    public function test_registered_public_schema_rejects_undeclared_nested_keys(): void
    {
        $rules = config('ai.schemas')['public_discovery.answer.v1']['rules'];

        $this->expectException(AiMalformedResponseException::class);
        (new LaravelStructuredOutputValidator)->validate([
            'answer' => 'Grounded answer.',
            'evidence' => [[
                'type' => 'product',
                'id' => '42',
                'reason' => 'Matches the budget.',
                'private_margin' => 'must-not-pass',
            ]],
        ], $rules);
    }

    public function test_registered_public_schema_returns_only_declared_top_level_keys(): void
    {
        $rules = config('ai.schemas')['public_discovery.answer.v1']['rules'];
        $validated = (new LaravelStructuredOutputValidator)->validate([
            'answer' => 'Grounded answer.',
            'evidence' => [[
                'type' => 'product',
                'id' => '42',
                'reason' => 'Matches the budget.',
            ]],
            'provider_debug' => 'must-not-pass',
        ], $rules);

        self::assertSame(['answer', 'evidence'], array_keys($validated));
    }
}
