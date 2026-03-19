<?php

declare(strict_types=1);

use App\Domain\Shared\Services\TenantContext;
use ReflectionNamedType;

/**
 * VC-AUDIT-008: TenantContext Security Test
 *
 * Verifies that setAsSystemRoot() enforces the isConsoleOrTesting flag guard.
 *
 * After refactor (removing app() service locator from Domain):
 * - The guard now relies on an explicit bool parameter instead of app()->runningInConsole().
 * - This removes the Laravel container dependency from the Domain layer (A5 compliance).
 * - Infrastructure callers (Console Kernel, TestCase, ServiceProvider) pass true explicitly.
 */
test('TenantContext setAsSystemRoot has HTTP context guard via explicit flag', function () {
    $context = new TenantContext;

    // Calling without flag (default false = HTTP context) must throw
    expect(fn () => $context->setAsSystemRoot(isConsoleOrTesting: false))
        ->toThrow(\RuntimeException::class, 'Security Violation');
});

test('TenantContext setAsSystemRoot succeeds when isConsoleOrTesting is true', function () {
    $context = new TenantContext;

    // Simulate console/job/test caller passing the explicit flag
    $context->setAsSystemRoot(isConsoleOrTesting: true);

    expect($context->isRoot())->toBeTrue();
    expect($context->isInitialized())->toBeTrue();
});

test('TenantContext guard prevents HTTP privilege escalation (signature check)', function () {
    $reflection = new ReflectionMethod(TenantContext::class, 'setAsSystemRoot');
    $params = $reflection->getParameters();

    // The method must have exactly one parameter: bool $isConsoleOrTesting = false
    expect($params)->toHaveCount(1);
    expect($params[0]->getName())->toBe('isConsoleOrTesting');
    $type = $params[0]->getType();
    expect($type instanceof ReflectionNamedType ? $type->getName() : null)->toBe('bool');

    // Verify the guard string is still present
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('Security Violation');
    expect($source)->toContain('cannot be invoked from HTTP context');
    expect($source)->toContain('isConsoleOrTesting');
});
