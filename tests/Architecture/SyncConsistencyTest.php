<?php

declare(strict_types=1);

/**
 * SyncConsistencyTest - Synchronous Execution Governance
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  Prevents race conditions caused by naive async refactoring of         ║
 * ║  commands that require "read-your-writes" consistency.                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

use App\Application\Identity\Commands\RegisterUserCommand;
use Illuminate\Contracts\Queue\ShouldQueue;

test('register user command is strictly synchronous', function () {
    // Critical: Fortify's CreatesNewUsers contract expects an immediate
    // Authenticatable return. If RegisterUserCommand implements ShouldQueue,
    // the user will not be persisted when getById() is called.
    $reflection = new ReflectionClass(RegisterUserCommand::class);

    expect($reflection->implementsInterface(ShouldQueue::class))
        ->toBeFalse('RegisterUserCommand must NOT implement ShouldQueue to prevent race conditions.');
});

test('create new user action documents sync requirement', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Identity/CreateNewUser.php');

    expect($content)->toContain('SYNC REQUIRED');
});
