<?php
$base = __DIR__ . '/app/Filament';
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
$count = 0;
foreach ($iter as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $original = file_get_contents($path);
    $updated = $original;
    // Dedupe Heroicon imports
    $matches = [];
    preg_match_all('/^use Filament\\\\Support\\\\Icons\\\\Heroicon;$/m', $updated, $matches, PREG_OFFSET_CAPTURE);
    if (count($matches[0]) > 1) {
        $updated = preg_replace('/(^use Filament\\\\Support\\\\Icons\\\\Heroicon;$\n)+/m', "use Filament\\Support\\Icons\\Heroicon;\n", $updated, 1);
    }
    // Dedupe Schema imports
    $matches = [];
    preg_match_all('/^use Filament\\\\Schemas\\\\Schema;$/m', $updated, $matches, PREG_OFFSET_CAPTURE);
    if (count($matches[0]) > 1) {
        $updated = preg_replace('/(^use Filament\\\\Schemas\\\\Schema;$\n)+/m', "use Filament\\Schemas\\Schema;\n", $updated, 1);
    }
    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $count++;
        echo "Deduped: " . basename($path) . "\n";
    }
}
echo "Total deduped: $count\n";
