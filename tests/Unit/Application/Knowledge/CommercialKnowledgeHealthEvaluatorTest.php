<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Knowledge;

use App\Application\Knowledge\CommercialKnowledgeHealthEvaluator;
use PHPUnit\Framework\TestCase;

final class CommercialKnowledgeHealthEvaluatorTest extends TestCase
{
    public function test_complete_verified_facts_are_publicly_resolvable(): void
    {
        $health = (new CommercialKnowledgeHealthEvaluator)->evaluate([
            'schema_complete' => true,
            'tenant_slug' => 'cafeteria',
            'playbook' => 'hybrid',
            'has_public_product' => true,
            'has_priced_product' => true,
            'has_public_service' => true,
            'has_priced_service' => true,
            'has_usable_hours' => true,
            'has_compatible_coverage' => true,
            'relevant_fact_updated_at' => '2026-08-01T00:00:00+00:00',
        ], new \DateTimeImmutable('2026-08-09T00:00:00+00:00'));

        self::assertSame(100, $health->score);
        self::assertTrue($health->publiclyResolvable);
        self::assertSame([], $health->issues);
    }

    public function test_missing_schema_is_explicitly_unavailable_instead_of_a_false_success(): void
    {
        $health = (new CommercialKnowledgeHealthEvaluator)->evaluate(
            ['schema_complete' => false],
            new \DateTimeImmutable('2026-08-09T00:00:00+00:00'),
        );

        self::assertSame(0, $health->score);
        self::assertFalse($health->publiclyResolvable);
        self::assertSame('knowledge_unavailable', $health->issues[0]['code']);
    }
}
