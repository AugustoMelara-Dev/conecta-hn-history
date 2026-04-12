<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Domain\TenantGovernance\Services\BusinessSegmentResolver;
use Tests\TestCase;

final class BusinessSegmentResolverTest extends TestCase
{
    public function test_slug_override_for_bufete_lex_hn_forces_law_firm_segment(): void
    {
        $resolver = app(BusinessSegmentResolver::class);

        $segment = $resolver->resolve(
            businessType: 'service',
            name: 'Bufete Lex HN',
            description: 'Servicios corporativos y compliance.',
            about: 'Firma legal para empresas.',
            slug: 'bufete-lex-hn',
            categorySlug: 'servicios',
            categoryName: 'Servicios',
        );

        $this->assertSame('law_firm', $segment);
    }

    public function test_slug_override_for_lex_corporativo_hn_forces_corporate_segment(): void
    {
        $resolver = app(BusinessSegmentResolver::class);

        $segment = $resolver->resolve(
            businessType: 'service',
            name: 'Lex Corporativo HN',
            description: 'Firma legal.',
            about: 'Servicios generales.',
            slug: 'lex-corporativo-hn',
            categorySlug: 'servicios',
            categoryName: 'Servicios',
        );

        $this->assertSame('corporate_law_firm', $segment);
    }
}

