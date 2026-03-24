<?php

declare(strict_types=1);

$commands = [
    [
        'name' => 'server',
        'command' => 'php artisan serve',
    ],
    [
        'name' => 'queue',
        'command' => 'php artisan queue:listen --tries=1',
    ],
];

if (function_exists('pcntl_fork')) {
    $commands[] = [
        'name' => 'logs',
        'command' => 'php artisan pail --timeout=0',
    ];
}

$commands[] = [
    'name' => 'vite',
    'command' => 'npm run dev',
];

$palette = ['#93c5fd', '#c4b5fd', '#fb7185', '#fdba74'];
$colors = array_slice($palette, 0, count($commands));

$segments = [
    'npx',
    'concurrently',
    '-c',
    escapeshellarg(implode(',', $colors)),
];

foreach ($commands as $command) {
    $segments[] = escapeshellarg($command['command']);
}

$segments[] = '--names';
$segments[] = escapeshellarg(implode(',', array_column($commands, 'name')));
$segments[] = '--kill-others';

$commandLine = implode(' ', $segments);

$isDryRun = in_array('--dry-run', $argv, true) || getenv('CODEX_DEV_RUNNER_DRY_RUN') !== false;

if ($isDryRun) {
    fwrite(STDOUT, $commandLine . PHP_EOL);

    exit(0);
}

passthru($commandLine, $exitCode);

exit($exitCode);
