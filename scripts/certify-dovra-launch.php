<?php

declare(strict_types=1);

/**
 * Standalone exact-artifact manifest for Program 14.
 *
 * This script intentionally avoids bootstrapping Laravel so that it can still
 * describe the repository state even when the current checkout cannot boot.
 */
$options = parseOptions($argv);
$root = normalizePath($options['root'] ?? dirname(__DIR__));
$configPath = normalizePath($options['config'] ?? $root.'/config/dovra/launch.php');
$config = require $configPath;
$expectedHead = $options['expected-head'] ?? null;
$claimScanExtras = $options['claims-scan-path'] ?? [];
if (! is_array($claimScanExtras)) {
    $claimScanExtras = [$claimScanExtras];
}

$headResult = runShell('git rev-parse HEAD', $root);
$branchResult = runShell('git rev-parse --abbrev-ref HEAD', $root);
$status = runShell('git status --porcelain=v1', $root);
$gitUnavailable = $headResult['code'] !== 0 || $branchResult['code'] !== 0 || $status['code'] !== 0;
$head = $gitUnavailable ? null : trim($headResult['output']);
$branch = $gitUnavailable ? null : trim($branchResult['output']);
$dirtyLines = $status['code'] === 0
    ? array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($status['output'])) ?: []))
    : [];
$dirtyPaths = [];

foreach ($dirtyLines as $line) {
    $path = trim(substr($line, 3));
    if ($path !== '') {
        $dirtyPaths[] = str_replace('\\', '/', $path);
    }
}

$lockFiles = [];
foreach ($config['manifest']['lock_files'] as $relativePath) {
    $lockFiles[] = describeFile($root, $relativePath);
}

$artifactFiles = [];
foreach ($config['manifest']['artifact_files'] as $relativePath) {
    $artifactFiles[] = describeFile($root, $relativePath);
}

$declaredPaths = auditDeclaredPaths($config, $root);
$claimScanPaths = array_values(array_unique(array_merge($config['manifest']['claims_scan_paths'], $claimScanExtras)));
$errors = [];
$exitCode = 0;
$headMismatch = ! $gitUnavailable && $expectedHead !== null && $expectedHead !== $head;

if ($gitUnavailable) {
    $errors[] = [
        'code' => 'git_unavailable',
        'head_exit_code' => $headResult['code'],
        'branch_exit_code' => $branchResult['code'],
        'status_exit_code' => $status['code'],
    ];
    $exitCode = max($exitCode, 3);
}

if ($headMismatch) {
    $errors[] = [
        'code' => 'head_mismatch',
        'expected' => $expectedHead,
        'actual' => $head,
    ];
    $exitCode = max($exitCode, 2);
}

if ($declaredPaths['missing_count'] > 0) {
    $errors[] = [
        'code' => 'missing_declared_paths',
        'missing_count' => $declaredPaths['missing_count'],
    ];
    $exitCode = max($exitCode, 8);
}

$brandScan = emptyScan(array_keys($config['manifest']['brand_patterns']));
$secretScan = emptyScan(array_keys($config['manifest']['secret_patterns']));
$unsupportedClaims = emptyClaimsScan();
$exactArtifactClean = ! $gitUnavailable && $dirtyPaths === [];

// HEAD mismatch and missing git are strict failures; avoid an expensive full
// tree scan when the artifact identity is already untrustworthy.
if (! $gitUnavailable && ! $headMismatch) {
    $brandScan = scanPatterns(
        $root,
        $config['manifest']['scan_roots'],
        $config['manifest']['brand_patterns'],
        limit: 40,
    );
    $secretScan = scanPatterns(
        $root,
        $config['manifest']['scan_roots'],
        $config['manifest']['secret_patterns'],
        limit: 40,
    );
    $unsupportedClaims = scanRegexList(
        $root,
        $claimScanPaths,
        $config['manifest']['unsupported_claim_patterns'],
        limit: 20,
    );
}

if ($unsupportedClaims['match_count'] > 0) {
    $errors[] = [
        'code' => 'unsupported_claims',
        'match_count' => $unsupportedClaims['match_count'],
    ];
    $exitCode = max($exitCode, 4);
}

$payload = [
    'manifest' => 'dovra-launch-certification',
    'generated_at_utc' => gmdate('c'),
    'repo_root' => $root,
    'config_path' => $configPath,
    'git_available' => ! $gitUnavailable,
    'branch' => $branch,
    'head' => $head,
    'expected_head' => $expectedHead,
    'dirty' => $dirtyPaths !== [],
    'dirty_path_count' => count($dirtyPaths),
    'dirty_paths' => $dirtyPaths,
    'dirty_paths_sample' => array_slice($dirtyPaths, 0, 25),
    'lock_files' => $lockFiles,
    'artifacts' => $artifactFiles,
    'declared_paths' => $declaredPaths,
    'scans' => [
        'brand' => $brandScan,
        'secrets' => $secretScan,
        'unsupported_claims' => $unsupportedClaims,
    ],
    'missing_evidence' => [
        'count' => $declaredPaths['missing_count'],
        'paths' => $declaredPaths['missing_paths'],
    ],
    'canonical_brand_matches' => $brandScan['totals']['canonical'] ?? 0,
    'legacy_brand_matches' => $brandScan['totals']['legacy'] ?? 0,
    'unsupported_claim_matches' => $unsupportedClaims['matches'],
    'manifest_failures' => $errors,
    'command_inventory' => [
        'acceptance_commands' => flattenCommands($config['acceptance']),
        'launch_gate_commands' => flattenCommands($config['gates']),
    ],
    'summary' => [
        'strict_failures' => array_column($errors, 'code'),
        'manifest_executed_without_strict_failures' => $exitCode === 0,
        'can_certify_exact_artifact' => $exitCode === 0 && $exactArtifactClean,
        'dirty_state_is_not_production_readiness' => true,
        'production_readiness' => 'not_evaluated_by_repository_certifier',
        'notes' => [
            'A dirty tree can produce an accounting manifest but cannot be certified as an exact immutable artifact.',
            'Repository certification is not public production, legal compliance, independent certification, traction, revenue, PMF, or investment readiness.',
        ],
    ],
    'errors' => $errors,
];

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;

exit($exitCode);

/**
 * @return array<string, mixed>
 */
function parseOptions(array $argv): array
{
    $parsed = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (! str_starts_with($argument, '--')) {
            continue;
        }

        $argument = substr($argument, 2);
        [$key, $value] = array_pad(explode('=', $argument, 2), 2, true);

        if (array_key_exists($key, $parsed)) {
            if (! is_array($parsed[$key])) {
                $parsed[$key] = [$parsed[$key]];
            }

            $parsed[$key][] = $value;

            continue;
        }

        $parsed[$key] = $value;
    }

    return $parsed;
}

function normalizePath(string $path): string
{
    $real = realpath($path);

    return str_replace('\\', '/', $real !== false ? $real : $path);
}

/**
 * @return array{code: int, output: string}
 */
function runShell(string $command, string $cwd): array
{
    $cwdArg = escapeshellarg($cwd);
    $commandLine = 'cd '.$cwdArg.' && '.$command.' 2>&1';
    $output = [];
    $code = 0;
    exec($commandLine, $output, $code);

    return [
        'code' => $code,
        'output' => implode(PHP_EOL, $output),
    ];
}

/**
 * @return array{path: string, exists: bool, type: string, sha256: string|null, bytes: int|null}
 */
function describeFile(string $root, string $relativePath): array
{
    $absolutePath = $root.'/'.str_replace('\\', '/', $relativePath);
    $exists = is_file($absolutePath) || is_dir($absolutePath);
    $isFile = is_file($absolutePath);

    return [
        'path' => str_replace('\\', '/', $relativePath),
        'exists' => $exists,
        'type' => $isFile ? 'file' : ($exists ? 'directory' : 'missing'),
        'sha256' => $isFile ? hash_file('sha256', $absolutePath) : null,
        'bytes' => $isFile ? filesize($absolutePath) ?: 0 : null,
    ];
}

/** @param list<string> $labels */
function emptyScan(array $labels): array
{
    return [
        'paths_scanned' => 0,
        'totals' => array_fill_keys($labels, 0),
        'matches' => [],
    ];
}

/** @return array{paths_scanned: int, match_count: int, matches: list<array<string, string>>} */
function emptyClaimsScan(): array
{
    return [
        'paths_scanned' => 0,
        'match_count' => 0,
        'matches' => [],
    ];
}

/**
 * @param  array<string, mixed>  $config
 * @return array{paths: list<array{path: string, exists: bool, type: string}>, missing_count: int, missing_paths: list<string>}
 */
function auditDeclaredPaths(array $config, string $root): array
{
    $paths = [];

    foreach (($config['programs'] ?? []) as $program) {
        if (is_string($program['evidence_path'] ?? null) && $program['evidence_path'] !== '') {
            $paths[] = $program['evidence_path'];
        }
    }

    foreach (['acceptance', 'gates'] as $section) {
        foreach ($config[$section] ?? [] as $row) {
            foreach (array_merge($row['authority_paths'] ?? [], $row['artifacts'] ?? []) as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }
    }

    foreach (['lock_files', 'artifact_files', 'scan_roots', 'claims_scan_paths'] as $section) {
        foreach ($config['manifest'][$section] ?? [] as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }
    }

    $paths = array_values(array_unique(array_map(
        static fn (string $path): string => str_replace('\\', '/', $path),
        $paths,
    )));
    $descriptions = [];
    $missing = [];

    foreach ($paths as $path) {
        $description = describeFile($root, $path);
        $descriptions[] = [
            'path' => $description['path'],
            'exists' => $description['exists'],
            'type' => $description['type'],
        ];

        if (! $description['exists']) {
            $missing[] = $description['path'];
        }
    }

    return [
        'paths' => $descriptions,
        'missing_count' => count($missing),
        'missing_paths' => $missing,
    ];
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<string>
 */
function flattenCommands(array $rows): array
{
    $commands = [];

    foreach ($rows as $row) {
        foreach ($row['commands'] ?? [] as $command) {
            $commands[] = $command;
        }
    }

    return array_values(array_unique($commands));
}

/**
 * @param  list<string>  $roots
 * @param  array<string, string>  $patterns
 * @return array<string, mixed>
 */
function scanPatterns(string $root, array $roots, array $patterns, int $limit): array
{
    $files = collectFiles($root, $roots);
    $totals = [];
    $matches = [];

    foreach (array_keys($patterns) as $label) {
        $totals[$label] = 0;
    }

    foreach ($files as $file) {
        $contents = readTextFile($file['absolute']);
        if ($contents === null) {
            continue;
        }

        foreach ($patterns as $label => $pattern) {
            $count = preg_match_all($pattern, $contents, $allMatches);
            if ($count === false || $count === 0) {
                continue;
            }

            $totals[$label] += $count;

            foreach (array_slice($allMatches[0], 0, max(1, min($count, 3))) as $match) {
                if (count($matches) >= $limit) {
                    break 2;
                }

                $matches[] = [
                    'label' => $label,
                    'path' => $file['relative'],
                    'match' => $match,
                ];
            }
        }
    }

    return [
        'paths_scanned' => count($files),
        'totals' => $totals,
        'matches' => $matches,
    ];
}

/**
 * @param  list<string>  $roots
 * @param  list<string>  $patterns
 * @return array<string, mixed>
 */
function scanRegexList(string $root, array $roots, array $patterns, int $limit): array
{
    $files = collectFiles($root, $roots);
    $matches = [];

    foreach ($files as $file) {
        $contents = readTextFile($file['absolute']);
        if ($contents === null) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $found, PREG_SET_ORDER) !== 0) {
                foreach ($found as $hit) {
                    if (count($matches) >= $limit) {
                        break 3;
                    }

                    $matches[] = [
                        'path' => $file['relative'],
                        'match' => $hit[0],
                    ];
                }
            }
        }
    }

    return [
        'paths_scanned' => count($files),
        'match_count' => count($matches),
        'matches' => $matches,
    ];
}

/**
 * @param  list<string>  $roots
 * @return list<array{absolute: string, relative: string}>
 */
function collectFiles(string $root, array $roots): array
{
    static $cache = [];
    $cacheKey = $root.'|'.json_encode(array_values($roots), JSON_UNESCAPED_SLASHES);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $files = [];
    $seen = [];

    foreach ($roots as $relativeRoot) {
        $relativeRoot = str_replace('\\', '/', $relativeRoot);
        $absoluteRoot = $root.'/'.$relativeRoot;

        if (is_file($absoluteRoot)) {
            $normalized = normalizePath($absoluteRoot);
            if (! isset($seen[$normalized]) && isTextCandidate($normalized)) {
                $seen[$normalized] = true;
                $files[] = [
                    'absolute' => $normalized,
                    'relative' => $relativeRoot,
                ];
            }

            continue;
        }

        if (! is_dir($absoluteRoot)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $normalized = normalizePath($file->getPathname());
            if (! isTextCandidate($normalized) || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $files[] = [
                'absolute' => $normalized,
                'relative' => str_replace('\\', '/', substr($normalized, strlen($root) + 1)),
            ];
        }
    }

    usort(
        $files,
        static fn (array $left, array $right): int => strcmp($left['relative'], $right['relative']),
    );

    $cache[$cacheKey] = $files;

    return $files;
}

function isTextCandidate(string $absolutePath): bool
{
    if (preg_match('#/(?:vendor|node_modules|\.git|bootstrap/cache|public/build)/#', str_replace('\\', '/', $absolutePath)) === 1) {
        return false;
    }

    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $allowed = [
        '', 'md', 'txt', 'json', 'jsonl', 'php', 'ps1', 'mjs', 'cjs', 'js', 'jsx', 'ts', 'tsx',
        'yml', 'yaml', 'xml', 'env', 'example', 'lock', 'neon',
    ];

    return in_array($extension, $allowed, true);
}

function readTextFile(string $path): ?string
{
    if (! is_file($path)) {
        return null;
    }

    $size = filesize($path);
    if ($size !== false && $size > 1_000_000) {
        return null;
    }

    $contents = file_get_contents($path);

    return is_string($contents) ? $contents : null;
}
