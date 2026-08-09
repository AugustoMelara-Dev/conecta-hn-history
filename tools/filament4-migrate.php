<?php

declare(strict_types=1);
$base = __DIR__.'/app/Filament';
$count = 0;
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iter as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $original = file_get_contents($path);
    $updated = $original;

    // 1. Add Heroicon import if needed
    if (preg_match("/navigationIcon = 'heroicon-/i", $updated) && ! str_contains($updated, 'use Filament\Support\Icons\Heroicon')) {
        $updated = preg_replace('/(use Filament\\\\Forms\\\\Form;)/', "use Filament\\Forms\\Form;\nuse Filament\\Support\\Icons\\Heroicon;", $updated, 1);
        $updated = preg_replace('/(use Filament\\\\Tables\\\\Table;)/', "use Filament\\Tables\\Table;\nuse Filament\\Support\\Icons\\Heroicon;", $updated, 1);
    }

    // 2. Add Schema import if needed
    if (str_contains($updated, 'Schema::make()') && ! str_contains($updated, 'use Filament\Schemas\Schema')) {
        $updated = preg_replace('/(use Filament\\\\Forms\\\\Form;)/', "use Filament\\Forms\\Form;\nuse Filament\\Schemas\\Schema;", $updated, 1);
    }

    // 3. Convert navigationIcon to Heroicon enum
    $updated = preg_replace_callback(
        "/protected static[ \\?string]+ \\\\\$navigationIcon = 'heroicon-([a-z]+)-([a-z-]+)'; ?/",
        function ($m) {
            $style = ucfirst($m[1]);
            $name = str_replace(' ', '', ucwords(str_replace('-', ' ', $m[2])));

            return "protected static BackedEnum|string|null \$navigationIcon = Heroicon::{$style}{$name};";
        },
        $updated
    );

    // 4. Form::make() -> Schema::make()
    $updated = str_replace('Form::make()', 'Schema::make()', $updated);

    // 5. Form $form to Schema $schema
    $updated = preg_replace('/public function form\(Form \\\$form\): Form/', 'public function form(Schema $schema): Schema', $updated);

    // 6. Forms\Components\*\\n -> Schemas\Components\\*
    foreach (['Section', 'Grid', 'Tabs', 'Tab', 'Fieldset', 'Wizard', 'Step', 'Utilities', 'Actions', 'Hidden'] as $comp) {
        $updated = str_replace("Filament\\Forms\\Components\\{$comp}", "Filament\\Schemas\\Components\\{$comp}", $updated);
    }

    // 7. Forms\Components\TextInput -> Forms\Components\TextInput (stays)
    // 8. Forms\Components\Select -> Forms\Components\Select (stays)
    // 9. Forms\Components\DateTimePicker -> Forms\Components\DateTimePicker (stays)

    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $count++;
        echo 'Migrated: '.basename($path)."\n";
    }
}
echo "Total: $count\n";
