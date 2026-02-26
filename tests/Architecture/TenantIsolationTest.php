<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

test('domain commands strictly enforce tenant isolation', function () {
    $finder = new Finder();
    $finder->files()->in(__DIR__ . '/../../app/Application')->name('*Command.php');

    foreach ($finder as $file) {
        $relativePath = $file->getRelativePathname();
        $class = 'App\\Application\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

        if (class_exists($class)) {
            // Exceptions
            if (in_array($class, [
                'App\Application\Identity\Commands\RegisterUserCommand',
                'App\Application\Tenant\Commands\RegisterTenantCommand',
            ])) {
                continue;
            }

            // Architecture audit requires structural binding to tenant context.
            // We accept 'tenantId' or 'requestingTenantId' properties, OR a getTenantId() method.
            
            $hasTenantId = property_exists($class, 'tenantId');
            $hasRequestingTenantId = property_exists($class, 'requestingTenantId');
            $hasGetter = method_exists($class, 'getTenantId');

            expect($hasTenantId || $hasRequestingTenantId || $hasGetter)
                ->toBeTrue("Command $class violates tenant isolation: missing tenantId/requestingTenantId property or getTenantId() method.");
        }
    }
});

test('sensitive commands do not queue', function () {
    $finder = new Finder();
    $finder->files()->in(__DIR__ . '/../../app/Application')->name('*.php');

    foreach ($finder as $file) {
        $relativePath = $file->getRelativePathname();
        $class = 'App\\Application\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            
            if ($reflection->implementsInterface(\App\Application\Shared\Contracts\SensitiveDataInterface::class)) {
                // Exceptions
                if (in_array($class, [
                    'App\Application\Identity\Commands\RegisterUserCommand',
                    'App\Application\Tenant\Commands\RegisterTenantCommand',
                ])) {
                    continue;
                }
                expect($class)->not->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class);
            }
        }
    }
});
