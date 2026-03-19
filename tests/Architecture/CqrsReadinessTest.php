<?php

declare(strict_types=1);

/**
 * CqrsReadinessTest - CQRS Read Path Governance
 *
 * Enforces:
 * - P0-CQRS: CreateNewUser uses UserReadProjection port (not User::findOrFail)
 * - P0-BIND: UserReadProjection is wired in AppServiceProvider
 * - P0-IMPL: EloquentUserReadProjection implements the port contract
 *
 * Validates the system is ready for read-model separation (sharding,
 * read replicas, or API-backed read models) without Action-layer changes.
 */
test('create new user action uses read projection port', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Identity/CreateNewUser.php');

    expect($content)
        ->toContain('UserReadProjection')
        ->toContain('getById')
        ->not()->toContain('return User::findOrFail');
});

test('user read projection binding exists in service provider', function () {
    $content = file_get_contents(__DIR__.'/../../app/Providers/AppServiceProvider.php');

    expect($content)
        ->toContain('UserReadProjection::class')
        ->toContain('EloquentUserReadProjection::class');
});

test('eloquent user read projection implements port contract', function () {
    $reflection = new ReflectionClass(
        \App\Infrastructure\Identity\Projections\EloquentUserReadProjection::class
    );

    expect($reflection->implementsInterface(
        \App\Application\Identity\Contracts\UserReadProjection::class
    ))->toBeTrue();

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->isReadOnly())->toBeTrue();
});
