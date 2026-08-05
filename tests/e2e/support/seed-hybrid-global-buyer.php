<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$repositoryRoot = dirname(__DIR__, 3);
$fail = static function (string $message): never {
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
};

if ($argc !== 1) {
    $fail('hybrid global buyer seed accepts no arguments');
}

$providedAppKey = getenv('APP_KEY');
if (! is_string($providedAppKey) || trim($providedAppKey) === '') {
    $fail('hybrid global buyer seed requires an explicit APP_KEY');
}

try {
    // Required invocation exports DB_CONNECTION=sqlite and DB_DATABASE to the
    // same absolute disposable file as DOVRA_E2E_DATABASE_PATH.
    require $repositoryRoot.'/vendor/autoload.php';
    $application = require $repositoryRoot.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    if (! $application->environment(['local', 'testing'])) {
        $fail('hybrid global buyer seed is restricted to local/testing');
    }
    if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER') !== 'true') {
        $fail('synthetic hybrid global buyer guard is missing');
    }
    if ((string) config('database.default') !== 'sqlite') {
        $fail('hybrid global buyer seed requires sqlite');
    }

    $configuredDatabase = (string) config('database.connections.sqlite.database');
    $expectedDatabase = (string) getenv('DOVRA_E2E_DATABASE_PATH');
    if ($configuredDatabase === '' || $configuredDatabase === ':memory:'
        || $expectedDatabase === '' || $expectedDatabase === ':memory:') {
        $fail('hybrid global buyer seed requires a file-backed disposable database');
    }
    $configuredRealPath = realpath($configuredDatabase);
    $expectedRealPath = realpath($expectedDatabase);
    if ($configuredRealPath === false || $expectedRealPath === false
        || $configuredRealPath !== $expectedRealPath
        || ! is_file($configuredRealPath)
        || filesize($configuredRealPath) < 1) {
        $fail('hybrid global buyer seed database path mismatch');
    }

    if (! class_exists(Tests\Support\HybridGlobalBuyerFixture::class)) {
        require_once $repositoryRoot.'/tests/Support/HybridGlobalBuyerFixture.php';
    }

    $state = Tests\Support\HybridGlobalBuyerFixture::seed();
    echo json_encode([
        'status' => 'ok',
        'fixture' => 'hybrid-global-buyer',
        'state' => $state,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
