<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BrandCleanCommand extends Command
{
    protected $signature = 'check:brand-clean {--path=* : Relative or absolute paths to scan}';

    protected $description = 'Fail when legacy brand terms appear in repo-owned text surfaces.';

    /** @var list<string> */
    private array $excludedDirectories = [
        '.git',
        'bootstrap/cache',
        'node_modules',
        'public/build',
        'public/css',
        'storage',
        'vendor',
    ];

    /** @var list<string> */
    private array $excludedFiles = [
        '.github/workflows/ci.yml',
        '.github/workflows/e2e-extended-nightly.yml',
        '.github/workflows/schema-governance.yml',
        '.github/workflows/security-dast.yml',
        'config/conecta.php',
        'docs/audits/critical-audit-2026-04-26.md',
        'docs/audits/startup-readiness-2026-04-26.md',
        'docs/qa/production-audit-2026-04-22.md',
        'docs/rebrand-technical-exceptions.md',
        'phpunit.xml',
        'scripts/e2e-server.mjs',
        'scripts/e2e-server.ps1',
        'scripts/run-concurrency-tests.ps1',
        'scripts/run-concurrency-tests.sh',
        'tests/bootstrap.php',
    ];

    /** @var list<string> */
    private array $excludedExtensions = [
        'avif',
        'gif',
        'ico',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'woff',
        'woff2',
        'zip',
    ];

    public function handle(): int
    {
        $paths = $this->option('path');
        $customPaths = is_array($paths) && $paths !== [];
        $roots = $customPaths ? $paths : [
            '.github',
            'app',
            'config',
            'database',
            'devops',
            'docs',
            'resources',
            'routes',
            'scripts',
            'supervisor',
            'tests/Feature',
            'vite.config.js',
            'README.md',
            'public/manifest.webmanifest',
        ];
        $failures = [];

        foreach ($roots as $root) {
            foreach ($this->filesIn((string) $root, ! $customPaths) as $file) {
                $contents = @file_get_contents($file->getPathname());

                if (! is_string($contents) || str_contains($contents, "\0")) {
                    continue;
                }

                foreach ($this->forbiddenTerms() as $term) {
                    if (str_contains($contents, $term)) {
                        $failures[] = $this->relativePath($file->getPathname()).' contains legacy brand term.';
                        break;
                    }
                }
            }
        }

        $failures = array_values(array_unique($failures));

        if ($failures === []) {
            $this->info('Brand clean: no legacy brand terms found.');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        return self::FAILURE;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function filesIn(string $root, bool $respectExclusions = true): iterable
    {
        $path = $this->absolutePath($root);

        if (is_file($path)) {
            yield new SplFileInfo($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($respectExclusions && $this->shouldSkip($file)) {
                continue;
            }

            yield $file;
        }
    }

    private function shouldSkip(SplFileInfo $file): bool
    {
        $extension = strtolower($file->getExtension());
        if (in_array($extension, $this->excludedExtensions, true)) {
            return true;
        }

        $relativePath = str_replace('\\', '/', $this->relativePath($file->getPathname()));

        foreach ($this->excludedDirectories as $directory) {
            if ($relativePath === $directory || str_starts_with($relativePath, $directory.'/')) {
                return true;
            }
        }

        if (in_array($relativePath, $this->excludedFiles, true)) {
            return true;
        }

        return false;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    private function relativePath(string $path): string
    {
        $base = str_replace('\\', '/', base_path());
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, $base.'/')
            ? substr($normalized, strlen($base) + 1)
            : $normalized;
    }

    /**
     * @return list<string>
     */
    private function forbiddenTerms(): array
    {
        $first = 'Con';
        $second = 'ecta';
        $third = 'Connect';

        return [
            $first.$second,
            strtoupper($first.$second),
            $third.'a',
            $first.$second.'Cn',
            $first.$second.'HN',
            strtolower($first.$second).'-hn',
            strtolower($first.$second).'hn',
            strtolower($first.$second).'.hn',
            strtoupper($first.$second).' HN',
        ];
    }
}
