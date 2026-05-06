<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('user can update password via encapsulated domain method', function () {
    $user = User::factory()->create(['password' => Hash::make('old_password')]);

    $newHashedPassword = Hash::make('new_secret');
    $user->updatePassword($newHashedPassword);

    $user->refresh();

    expect(Hash::check('new_secret', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBeNull();
});

test('updatePassword rotates remember_token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('original'),
        'remember_token' => 'ORIGINAL_TOKEN',
    ]);

    $user->updatePassword(Hash::make('updated'));

    $user->refresh();

    expect($user->remember_token)->not->toBe('ORIGINAL_TOKEN')
        ->and($user->remember_token)->toHaveLength(60);
});
