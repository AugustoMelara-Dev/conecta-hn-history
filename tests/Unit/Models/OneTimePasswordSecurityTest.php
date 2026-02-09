<?php

declare(strict_types=1);

use App\Models\OneTimePassword;

/**
 * VC-AUDIT-103 Test Suite
 * 
 * Ensures OneTimePassword model guards sensitive fields against mass assignment.
 */

describe('VC-AUDIT-103: OneTimePassword Mass Assignment Protection', function () {
    it('guards sensitive fields from mass assignment', function () {
        $guarded = (new OneTimePassword())->getGuarded();
        
        expect($guarded)
            ->toContain('id')
            ->toContain('user_id')
            ->toContain('created_at')
            ->toContain('updated_at');
    });

    it('does not have empty guarded array', function () {
        $guarded = (new OneTimePassword())->getGuarded();
        
        expect($guarded)->not->toBeEmpty();
    });

    it('prevents user_id from being mass assigned', function () {
        $reflection = new ReflectionProperty(OneTimePassword::class, 'guarded');
        
        expect($reflection->getDefaultValue())
            ->toContain('user_id');
    });
});
