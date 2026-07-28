<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Product;

use App\Support\Product\ProductIdentity;
use Tests\TestCase;

final class ProductIdentityTest extends TestCase
{
    public function test_product_identity_contract_exists(): void
    {
        self::assertTrue(class_exists(ProductIdentity::class));
    }

    public function test_product_identity_exposes_canonical_configured_facts(): void
    {
        foreach ([
            'name',
            'shortName',
            'category',
            'commercialPromise',
            'originMarket',
            'legalEntityName',
            'nameClearanceConfirmed',
        ] as $method) {
            self::assertTrue(
                method_exists(ProductIdentity::class, $method),
                "ProductIdentity::{$method}() is required.",
            );
        }

        self::assertSame('Dovra AI', ProductIdentity::name());
        self::assertSame('Dovra', ProductIdentity::shortName());
        self::assertSame('Global Conversational Business OS', ProductIdentity::category());
        self::assertSame(
            'Dovra deploys AI employees that know a business, work through its channels, and turn conversations into measurable results.',
            ProductIdentity::commercialPromise(),
        );
        self::assertSame('HN', ProductIdentity::originMarket());
        self::assertNull(ProductIdentity::legalEntityName());
        self::assertFalse(ProductIdentity::nameClearanceConfirmed());
    }

    public function test_legal_entity_is_explicit_and_never_inferred_from_product_name(): void
    {
        self::assertTrue(method_exists(ProductIdentity::class, 'legalEntityName'));
        self::assertTrue(method_exists(ProductIdentity::class, 'name'));

        config()->set('dovra.legal.entity_name', 'Conecta HN S.A. de C.V.');

        self::assertSame('Conecta HN S.A. de C.V.', ProductIdentity::legalEntityName());
        self::assertNotSame(ProductIdentity::name(), ProductIdentity::legalEntityName());
    }
}
