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
        $fillable = (new OneTimePassword)->getFillable();

        expect(in_array('id', $fillable, true))->toBeFalse();
        expect(in_array('created_at', $fillable, true))->toBeFalse();
        expect(in_array('updated_at', $fillable, true))->toBeFalse();
        expect($fillable)->toContain('user_id');
        expect($fillable)->toContain('identifier');
        expect($fillable)->toContain('code');
        expect($fillable)->toContain('expires_at');
    });

    it('does not have empty fillable array', function () {
        $fillable = (new OneTimePassword)->getFillable();

        expect($fillable)->not->toBeEmpty();
    });

    it('keeps sensitive framework-managed fields out of fillable', function () {
        $reflection = new ReflectionProperty(OneTimePassword::class, 'fillable');
        /** @var array<int, string> $fillable */
        $fillable = $reflection->getDefaultValue();

        expect(in_array('id', $fillable, true))->toBeFalse();
        expect(in_array('created_at', $fillable, true))->toBeFalse();
        expect(in_array('updated_at', $fillable, true))->toBeFalse();
    });
});
