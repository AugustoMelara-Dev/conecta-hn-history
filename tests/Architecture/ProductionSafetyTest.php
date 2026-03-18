<?php

declare(strict_types=1);

/**
 * ProductionSafetyTest - Assert Erasure Prevention
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEC-01: Prevents use of assert() for runtime type validation.        ║
 * ║  In production (zend.assertions = -1), assert() is compiled to noop.  ║
 * ║  Actions MUST use explicit if/throw guard clauses instead.            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
test('actions do not use assert for runtime validation', function () {
    $dir = realpath(__DIR__.'/../../app/Actions');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $scanned = 0;

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relative = str_replace($dir.DIRECTORY_SEPARATOR, '', $file->getPathname());

        // Detect assert() as executable code, NOT inside comments
        // Matches lines where assert( appears and is NOT preceded by // or * (comment)
        $hasExecutableAssert = (bool) preg_match('/^\s*assert\s*\(/m', $content);

        expect($hasExecutableAssert)->toBeFalse(
            "File [{$relative}] contains executable assert(). Use explicit if/throw guard clauses instead."
        );

        $scanned++;
    }

    expect($scanned)->toBeGreaterThan(0, 'No PHP files found in Actions directory.');
});

test('actions use explicit TenantAwareAuthenticatable contract guards', function () {
    $actionFiles = [
        realpath(__DIR__.'/../../app/Actions/Authentication/UpdateUserPassword.php'),
        realpath(__DIR__.'/../../app/Actions/Identity/UpdateUserProfileInformation.php'),
        realpath(__DIR__.'/../../app/Actions/Authentication/ResetPasswordAction.php'),
    ];

    $checked = 0;

    foreach ($actionFiles as $path) {
        if (! $path || ! file_exists($path)) {
            continue;
        }

        $content = file_get_contents($path);
        $basename = basename($path);

        // Check for explicit instanceof guard clause (regex handles \r\n and whitespace)
        $hasGuard = (bool) preg_match(
            '/if\s*\(\s*!\s*\$user\s+instanceof\s+TenantAwareAuthenticatable\s*\)/',
            $content
        );
        expect($hasGuard)->toBeTrue(
            "File [{$basename}] must use explicit instanceof guard clause"
        );

        // Check for explicit exception throw
        $hasThrow = (bool) preg_match(
            '/throw\s+new\s+\\\\?InvalidArgumentException/',
            $content
        );
        expect($hasThrow)->toBeTrue(
            "File [{$basename}] must throw InvalidArgumentException on contract violation"
        );

        $checked++;
    }

    expect($checked)->toBe(3, 'Expected exactly 3 Action files with contract guards.');
});
