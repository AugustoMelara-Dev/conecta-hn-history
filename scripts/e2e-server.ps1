$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$env:APP_ENV = 'testing'
$env:APP_URL = 'http://127.0.0.1:8011'
$env:APP_KEY = 'base64:2fl+Ktvkfl+Fuz4Qk/NaWvy1Pt7EQqHMA2wc/ulkZyo='
$env:CACHE_STORE = 'array'
$env:QUEUE_CONNECTION = 'database'
$env:SESSION_DRIVER = 'file'
$env:SESSION_DOMAIN = ''
$env:SESSION_SECURE_COOKIE = 'false'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = if ($env:E2E_DB_HOST) { $env:E2E_DB_HOST } else { '127.0.0.1' }
$env:DB_PORT = if ($env:E2E_DB_PORT) { $env:E2E_DB_PORT } else { '3306' }
$env:DB_DATABASE = if ($env:E2E_DB_DATABASE) { $env:E2E_DB_DATABASE } else { 'conecta_hn_e2e_testing' }
$env:DB_USERNAME = if ($env:E2E_DB_USERNAME) { $env:E2E_DB_USERNAME } else { 'root' }
$env:DB_PASSWORD = if ($env:E2E_DB_PASSWORD) { $env:E2E_DB_PASSWORD } else { '' }

$databaseBootstrapPath = Join-Path $root '.tmp-e2e-db-bootstrap.php'

$databaseBootstrapScript = @'
<?php
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'conecta_hn_e2e_testing';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

$pdo = new PDO("mysql:host={$host};port={$port}", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
'@

$databaseBootstrapScript | Set-Content -Path $databaseBootstrapPath

php $databaseBootstrapPath
if ($LASTEXITCODE -ne 0) {
    Remove-Item $databaseBootstrapPath -Force -ErrorAction SilentlyContinue
    exit $LASTEXITCODE
}

Remove-Item $databaseBootstrapPath -Force -ErrorAction SilentlyContinue

php artisan optimize:clear --env=testing
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan migrate:fresh --env=testing --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan db:seed --class="Database\Seeders\E2EStorefrontSeeder" --env=testing --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan serve --env=testing --host=127.0.0.1 --port=8011 --no-reload
