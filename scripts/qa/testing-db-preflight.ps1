param(
    [string] $Configuration = "phpunit.xml",
    [switch] $Repair
)

$ErrorActionPreference = "Stop"

function Fail($Message) {
    Write-Host "[testing-db-preflight] ERROR: $Message" -ForegroundColor Red
    exit 1
}

function Info($Message) {
    Write-Host "[testing-db-preflight] $Message" -ForegroundColor Cyan
}

$configPath = Resolve-Path -Path $Configuration -ErrorAction SilentlyContinue
if (-not $configPath) {
    Fail "PHPUnit configuration not found: $Configuration"
}

[xml] $phpunit = Get-Content -Raw -Path $configPath
$envNodes = $phpunit.phpunit.php.env
if (-not $envNodes) {
    Fail "No <php><env ... /></php> entries found in $Configuration"
}

foreach ($node in $envNodes) {
    $name = [string] $node.name
    $value = [string] $node.value
    if ($name) {
        [Environment]::SetEnvironmentVariable($name, $value, "Process")
    }
}

$dbConnection = [Environment]::GetEnvironmentVariable("DB_CONNECTION", "Process")
$dbDatabase = [Environment]::GetEnvironmentVariable("DB_DATABASE", "Process")
$appEnv = [Environment]::GetEnvironmentVariable("APP_ENV", "Process")

if ($appEnv -ne "testing") {
    Fail "APP_ENV must be testing, got '$appEnv'. Refusing to continue."
}

if ([string]::IsNullOrWhiteSpace($dbConnection)) {
    Fail "DB_CONNECTION is empty in $Configuration"
}

if ([string]::IsNullOrWhiteSpace($dbDatabase)) {
    Fail "DB_DATABASE is empty in $Configuration"
}

$isMemorySqlite = $dbConnection -eq "sqlite" -and $dbDatabase -eq ":memory:"
$looksLikeTestingDb = $isMemorySqlite -or $dbDatabase -match "(^|[_-])(test|testing)([_-]|$)"

if (-not $looksLikeTestingDb) {
    Fail "DB_DATABASE '$dbDatabase' does not look like a testing database. Refusing to continue."
}

Info "Configuration: $Configuration"
Info "Connection: $dbConnection"
Info "Database: $dbDatabase"

if ($Repair) {
    Info "Repair requested. Running migrate:fresh only after testing database guard passed."
    php artisan migrate:fresh --force --no-ansi
    if ($LASTEXITCODE -ne 0) {
        Fail "migrate:fresh failed."
    }
}

Info "Checking migration status..."
php artisan migrate:status --no-ansi
if ($LASTEXITCODE -ne 0) {
    Fail "Migration status failed. Run this script with -Repair only if this is a disposable testing database."
}

Info "Testing database preflight passed."
