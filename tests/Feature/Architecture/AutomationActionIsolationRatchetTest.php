<?php

declare(strict_types=1);

use App\Application\Automation\ActionRegistry;
use App\Application\Automation\ConditionRegistry;
use App\Application\Automation\GraphBuilder;
use App\Application\Automation\TriggerRegistry;
use Illuminate\Support\Facades\File;

/**
 * PR3 Architecture Ratchet — Automation Action isolation.
 *
 * Las acciones de workflow solo pueden mutar estado dentro de un WorkflowRun.
 * Garbage: ninguna Action debe mutar entidades fuera de su contexto controlado.
 */
it('Automation Domain files do not import Filament', function () {
    $domainDir = app_path('Domain/Automation');
    expect(is_dir($domainDir))->toBeTrue();

    $files = File::allFiles($domainDir);

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = File::get($file->getPathname());
        expect($contents)->not->toContain('Filament\\');
    }
});

it('Automation Application uses strict types', function () {
    $domainDir = app_path('Domain/Automation');
    $appDir = app_path('Application/Automation');
    foreach ([$domainDir, $appDir] as $dir) {
        $files = File::allFiles($dir);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = File::get($file->getPathname());
            expect($contents)->toContain('declare(strict_types=1);');
        }
    }
});

it('TriggerRegistry / ActionRegistry / ConditionRegistry exist', function () {
    expect(class_exists(TriggerRegistry::class))->toBeTrue();
    expect(class_exists(ActionRegistry::class))->toBeTrue();
    expect(class_exists(ConditionRegistry::class))->toBeTrue();
});

it('GraphBuilder enforces single trigger', function () {
    $builder = new GraphBuilder;

    // 0 triggers
    $result = $builder->build(
        [['id' => 'a1', 'type' => 'action', 'action_type' => 'send_email']],
        []
    );
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('Workflow must have at least one trigger node');
});
