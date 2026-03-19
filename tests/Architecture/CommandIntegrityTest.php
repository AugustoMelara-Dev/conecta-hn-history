<?php

declare(strict_types=1);

/**
 * CommandIntegrityTest - Persistence Decoupling Governance
 *
 * Enforces that Application Layer Commands carry only primitives,
 * DTOs, and Value Objects — never Eloquent models.
 */
test('reset user password command does not carry eloquent model', function () {
    $class = new ReflectionClass(\App\Application\Auth\Commands\ResetUserPasswordCommand::class);

    /** @var list<class-string> $nonBuiltinTypes */
    $nonBuiltinTypes = [];

    foreach ($class->getProperties() as $property) {
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $nonBuiltinTypes[] = $type->getName();
        }
    }

    expect($nonBuiltinTypes)->not()->toContain('App\Models\User');
});

test('reset password action constructs command with scalar userId', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Authentication/ResetPasswordAction.php');
    expect($content)
        ->toContain('$user->id')
        ->not()->toContain('user: $user');
});
