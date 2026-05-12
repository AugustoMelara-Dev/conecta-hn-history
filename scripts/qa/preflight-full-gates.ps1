Param(
    [switch]$SkipMigrateFresh,
    [switch]$SkipBuild,
    [switch]$SkipConfigCache
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$root = Split-Path -Parent $root
Set-Location $root

function Assert-MySqlTestingConfig {
    $phpunit = Join-Path $root "phpunit.xml"
    if (-not (Test-Path $phpunit)) {
        throw "No se encontro phpunit.xml para validar DB de testing."
    }

    $xml = Get-Content $phpunit -Raw
    if ($xml -notmatch 'name="DB_CONNECTION"\s+value="mysql"') {
        throw "Policy violation: testing debe usar MySQL. SQLite no esta permitido para este gate."
    }
}

function Run-Step {
    param(
        [string]$Label,
        [string]$Command
    )

    Write-Host ""
    Write-Host "==> $Label" -ForegroundColor Cyan
    Write-Host "    $Command"
    Invoke-Expression $Command

    if ($LASTEXITCODE -ne 0) {
        throw "Paso fallido: $Label"
    }
}

Assert-MySqlTestingConfig

Run-Step -Label "Clear caches (safe for testing)" -Command "php artisan config:clear; php artisan cache:clear; php artisan route:clear; php artisan view:clear"

if (-not $SkipMigrateFresh) {
    Run-Step -Label "Testing DB reset (migrate:fresh --seed)" -Command "php artisan migrate:fresh --seed --env=testing --force"
}

Run-Step -Label "Full test suite" -Command "php artisan test --colors=never"

if (-not $SkipBuild) {
    Run-Step -Label "Frontend build" -Command "npm.cmd run build"
}

if (-not $SkipConfigCache) {
    Run-Step -Label "Production config cache gate" -Command "php artisan config:cache"
}

Write-Host ""
Write-Host "OK: preflight + full gates completado." -ForegroundColor Green
