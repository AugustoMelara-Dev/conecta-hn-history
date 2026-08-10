<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class FilamentV4SurfaceContractTest extends TestCase
{
    public function test_filament_surfaces_do_not_reference_removed_v3_schema_or_action_namespaces(): void
    {
        $removedNamespaces = [
            'Filament\\Tables\\Actions',
            'Filament\\Forms\\Form',
            'Filament\\Forms\\Get',
            'Filament\\Forms\\Set',
            'Filament\\Forms\\Components\\Section',
            'Filament\\Forms\\Components\\Tabs',
            'Filament\\Forms\\Components\\Grid',
        ];

        $violations = [];

        foreach (File::allFiles(app_path('Filament')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = File::get($file->getPathname());

            foreach ($removedNamespaces as $namespace) {
                if (str_contains($source, $namespace)) {
                    $violations[] = $file->getRelativePathname().': '.$namespace;
                }
            }
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }
}
