<?php

declare(strict_types=1);

/**
 * VendorDecouplingTest - ORM & Bus Lock-in Prevention
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  NEGATIVE ARCHITECTURE TEST: Forbids Eloquent Model inheritance and   ║
 * ║  Bus Facade usage in App\Actions. Ensures Hexagonal Port contracts    ║
 * ║  (CommandBusInterface) are used instead of framework concretions.     ║
 * ║  EXCEPTION: Laravel\Fortify\Contracts are whitelisted (Driving Adapter)║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1. ORM LOCK-IN: Null
// ─────────────────────────────────────────────────────────────────────────────

test('actions do not depend on Eloquent models directly', function () {
    $dir = realpath(__DIR__ . '/../../app/Actions');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $scanned = 0;

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relative = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());

        $importsEloquent = str_contains($content, 'use Illuminate\Database\Eloquent\Model');
        expect($importsEloquent)->toBeFalse(
            "File [{$relative}] imports Eloquent Model. Use Domain/Application DTOs or Contracts."
        );

        // Detect direct class inheritance from Model
        $extendsModel = (bool) preg_match('/class\s+\w+\s+extends\s+Model\b/', $content);
        expect($extendsModel)->toBeFalse(
            "File [{$relative}] extends Eloquent Model. Actions must not be ORM entities."
        );

        $scanned++;
    }

    expect($scanned)->toBeGreaterThan(0, 'No PHP files found in Actions directory.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. BUS LOCK-IN: Null
// ─────────────────────────────────────────────────────────────────────────────

test('actions use CommandBusInterface instead of Bus facade', function () {
    $dir = realpath(__DIR__ . '/../../app/Actions');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $scanned = 0;

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relative = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());

        $importsBusFacade = str_contains($content, 'use Illuminate\Support\Facades\Bus');
        expect($importsBusFacade)->toBeFalse(
            "File [{$relative}] imports Bus Facade. Use CommandBusInterface port."
        );

        $usesBusFacade = (bool) preg_match('/\bBus::dispatch\b/', $content);
        expect($usesBusFacade)->toBeFalse(
            "File [{$relative}] calls Bus::dispatch(). Use injected CommandBusInterface."
        );

        $scanned++;
    }

    expect($scanned)->toBeGreaterThan(0, 'No PHP files found in Actions directory.');
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. CONTRACT VERIFICATION: Proper DI for dispatching
// ─────────────────────────────────────────────────────────────────────────────

test('actions that dispatch rely on CommandBusInterface injection', function () {
    $dir = realpath(__DIR__ . '/../../app/Actions');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $checked = 0;

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relative = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());

        // Only enforce on Actions that call ->dispatch(
        if (str_contains($content, '->dispatch(')) {
            $hasInterface = str_contains($content, 'CommandBusInterface');
            expect($hasInterface)->toBeTrue(
                "File [{$relative}] calls dispatch() but does not inject CommandBusInterface."
            );
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(0, 'No Actions found that call dispatch() — expected at least one.');
});
