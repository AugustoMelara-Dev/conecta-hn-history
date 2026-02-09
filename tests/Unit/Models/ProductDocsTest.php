<?php

declare(strict_types=1);

use App\Models\Product;

/**
 * VC-AUDIT-105 Test Suite
 * 
 * Ensures Product model static methods have scope bypass documentation.
 */

describe('VC-AUDIT-105: Product Scope Bypass Documentation', function () {
    it('has scope bypass warning in getAdminList PHPDoc', function () {
        $reflection = new ReflectionMethod(Product::class, 'getAdminList');
        $docComment = $reflection->getDocComment();
        
        expect($docComment)
            ->toContain('VC-AUDIT-105')
            ->and($docComment)
            ->toContain('SCOPE BYPASS WARNING');
    });

    it('has scope bypass warning in countAllForTenant PHPDoc', function () {
        $reflection = new ReflectionMethod(Product::class, 'countAllForTenant');
        $docComment = $reflection->getDocComment();
        
        expect($docComment)
            ->toContain('VC-AUDIT-105')
            ->and($docComment)
            ->toContain('SCOPE BYPASS WARNING');
    });

    it('has scope bypass warning in findInTenant PHPDoc', function () {
        $reflection = new ReflectionMethod(Product::class, 'findInTenant');
        $docComment = $reflection->getDocComment();
        
        expect($docComment)
            ->toContain('VC-AUDIT-105')
            ->and($docComment)
            ->toContain('SCOPE BYPASS WARNING');
    });

    it('has scope bypass warning in searchForPos PHPDoc', function () {
        $reflection = new ReflectionMethod(Product::class, 'searchForPos');
        $docComment = $reflection->getDocComment();
        
        expect($docComment)
            ->toContain('VC-AUDIT-105')
            ->and($docComment)
            ->toContain('SCOPE BYPASS WARNING');
    });
});
