<?php

declare(strict_types=1);

use App\Models\OneTimePassword;

/**
 * VC-AUDIT-103 Test Suite
 * 
 * Ensures OneTimePassword model guards sensitive fields against mass assignment.
 */

describe('VC-AUDIT-103: OneTimePassword Mass Assignment Protection', function () {
    it('uses explicit fillable array instead of guarded', function () {
        $fillable = (new OneTimePassword())->getFillable();

        expect($fillable)
            ->not->toContain('id')
            ->not->toContain('created_at')
            ->not->toContain('updated_at')
            ->toContain('user_id')
            ->toContain('identifier')
            ->toContain('code')
            ->toContain('expires_at');
    });

    it('does not have empty fillable array', function () {
        $fillable = (new OneTimePassword())->getFillable();

        expect($fillable)->not->toBeEmpty();
    });

    it('keeps sensitive framework-managed fields out of fillable', function () {
        $reflection = new ReflectionProperty(OneTimePassword::class, 'fillable');

        expect($reflection->getDefaultValue())
            ->not->toContain('id')
            ->not->toContain('created_at')
            ->not->toContain('updated_at');
    });
});
