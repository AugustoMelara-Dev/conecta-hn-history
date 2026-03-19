<?php

declare(strict_types=1);

/**
 * CriticalFixesTest - Production Bug & Rogue Adapter Governance
 *
 * Validates that critical runtime errors have been resolved and
 * architectural compliance is maintained.
 */
test('interaction controller imports correct action namespace', function () {
    $content = file_get_contents(__DIR__.'/../../app/Http/Controllers/Public/InteractionController.php');
    expect($content)
        ->toContain('use App\Infrastructure\Interactions\Actions\RecordInteractionAction;')
        ->not()->toContain('App\Application\Actions');
});

test('interaction controller uses InteractionData DTO not RecordInteractionResult', function () {
    $content = file_get_contents(__DIR__.'/../../app/Http/Controllers/Public/InteractionController.php');
    expect($content)
        ->toContain('use App\Application\Interactions\DTOs\InteractionData;')
        ->toContain('new InteractionData(')
        ->not()->toContain('RecordInteractionResult::forRedirect');
});

test('reset password action delegates to command bus with scalar userId', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Authentication/ResetPasswordAction.php');
    expect($content)
        ->toContain('CommandBusInterface')
        ->toContain('ResetUserPasswordCommand')
        ->toContain('userId:')
        ->not()->toContain('user: $user')
        ->not()->toContain('$user->updatePassword')
        ->not()->toContain('Hash::make');
});

test('reset user password handler exists in auth namespace', function () {
    expect(class_exists(\App\Application\Auth\Handlers\ResetUserPasswordHandler::class))->toBeTrue();
    expect((new ReflectionClass(\App\Application\Auth\Handlers\ResetUserPasswordHandler::class))->getNamespaceName())
        ->toBe('App\Application\Auth\Handlers');
});
