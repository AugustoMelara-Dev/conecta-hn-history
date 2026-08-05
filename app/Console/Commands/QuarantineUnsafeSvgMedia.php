<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class QuarantineUnsafeSvgMedia extends Command
{
    protected $signature = 'media:quarantine-unsafe-svg
        {--execute : Move unsafe objects to private quarantine and detach database references}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Detect and quarantine legacy SVG uploads from the same-origin public media disk.';

    /** @var array<string, list<string>> */
    private const REFERENCE_COLUMNS = [
        'tenants' => ['logo_path', 'banner_path', 'hero_image', 'cover_image_path', 'cover_path'],
        'categories' => ['icon_svg'],
        'products' => ['image_path'],
        'cities' => ['hero_image'],
        'announcements' => ['image_path'],
    ];

    public function handle(): int
    {
        $public = Storage::disk('public');
        $unsafePaths = [];

        foreach ($public->allFiles() as $path) {
            if ($this->isUnsafeSvg($public, $path)) {
                $unsafePaths[] = $path;
            }
        }

        sort($unsafePaths);
        $executed = (bool) $this->option('execute');
        $manifest = [];
        $detachedReferences = 0;
        $deletedMediaRows = 0;

        if ($executed && $unsafePaths !== []) {
            foreach ($unsafePaths as $path) {
                $quarantinePath = $this->moveToPrivateQuarantine($public, $path);
                $manifest[] = [
                    'original_path' => $path,
                    'quarantine_path' => $quarantinePath,
                    'sha256' => hash('sha256', $path),
                ];
            }

            $detachedReferences = $this->detachUnsafeReferences($unsafePaths);
            $deletedMediaRows = $this->deleteUnsafeMediaRows();
            $this->writeManifest($manifest, $detachedReferences, $deletedMediaRows);
        }

        $payload = [
            'status' => $unsafePaths === [] ? 'clean' : ($executed ? 'quarantined' : 'unsafe_media_detected'),
            'mode' => $executed ? 'execute' : 'dry-run',
            'unsafe_objects' => count($unsafePaths),
            'detached_references' => $detachedReferences,
            'deleted_media_rows' => $deletedMediaRows,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line("Unsafe public SVG objects: {$payload['unsafe_objects']}.");
            $this->line($executed ? 'Unsafe objects were moved to private quarantine.' : 'Dry run only; use --execute during the controlled release window.');
        }

        return $unsafePaths === [] || $executed ? self::SUCCESS : self::FAILURE;
    }

    private function isUnsafeSvg(FilesystemAdapter $disk, string $path): bool
    {
        if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['svg', 'svgz'], true)) {
            return true;
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return false;
        }

        try {
            $head = fread($stream, 65536);
        } finally {
            fclose($stream);
        }

        return is_string($head) && preg_match('/<svg(?:\s|>)/i', $head) === 1;
    }

    private function moveToPrivateQuarantine(FilesystemAdapter $public, string $path): string
    {
        $name = Str::slug(pathinfo($path, PATHINFO_FILENAME));
        $name = $name !== '' ? $name : 'unnamed';
        $quarantinePath = 'quarantine/unsafe-svg/'.hash('sha256', $path).'-'.$name.'.quarantined';
        $stream = $public->readStream($path);

        if (! is_resource($stream)) {
            throw new \RuntimeException("Unable to read unsafe media object: {$path}");
        }

        try {
            if (! Storage::disk('local')->put($quarantinePath, $stream)) {
                throw new \RuntimeException("Unable to write private quarantine object for: {$path}");
            }
        } finally {
            fclose($stream);
        }

        if (! $public->delete($path)) {
            Storage::disk('local')->delete($quarantinePath);
            throw new \RuntimeException("Unable to remove unsafe public media object: {$path}");
        }

        return $quarantinePath;
    }

    /** @param list<string> $unsafePaths */
    private function detachUnsafeReferences(array $unsafePaths): int
    {
        $updated = 0;

        foreach (self::REFERENCE_COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $updated += DB::table($table)
                    ->whereIn($column, $unsafePaths)
                    ->update([$column => null]);
            }
        }

        return $updated;
    }

    private function deleteUnsafeMediaRows(): int
    {
        if (! Schema::hasTable('media') || ! Schema::hasColumn('media', 'mime_type')) {
            return 0;
        }

        return DB::table('media')
            ->where('mime_type', 'image/svg+xml')
            ->orWhere('file_name', 'like', '%.svg')
            ->orWhere('file_name', 'like', '%.svgz')
            ->delete();
    }

    /** @param list<array{original_path: string, quarantine_path: string, sha256: string}> $objects */
    private function writeManifest(array $objects, int $detachedReferences, int $deletedMediaRows): void
    {
        $timestamp = now('UTC')->format('Ymd\THis\Z');
        $manifestPath = "quarantine/unsafe-svg/manifests/{$timestamp}-".Str::uuid().'.json';
        $manifest = [
            'created_at_utc' => now('UTC')->toIso8601String(),
            'objects' => $objects,
            'detached_references' => $detachedReferences,
            'deleted_media_rows' => $deletedMediaRows,
            'restore_policy' => 'Manual security review and rasterization are required; never restore SVG directly to a public disk.',
        ];

        Storage::disk('local')->put(
            $manifestPath,
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }
}
