Param(
    [switch]$SkipFullSuite
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
        throw "Policy violation: testing debe usar MySQL. SQLite no esta permitido para este runner."
    }
}

function Run-TestBlock {
    param(
        [string]$Label,
        [string]$Command
    )

    Write-Host ""
    Write-Host "==> $Label" -ForegroundColor Cyan
    Write-Host "    $Command"
    Invoke-Expression $Command
    if ($LASTEXITCODE -ne 0) {
        throw "Bloque fallido: $Label"
    }
}

Assert-MySqlTestingConfig

Run-TestBlock -Label "Feature/Auth/PasswordUpdateTest" -Command "php artisan test tests/Feature/Auth/PasswordUpdateTest.php --colors=never"
Run-TestBlock -Label "Feature/Auth/SocialLoginTest" -Command "php artisan test tests/Feature/Auth/SocialLoginTest.php --colors=never"
Run-TestBlock -Label "Feature/Auth/TelemetryIntegrationTest" -Command "php artisan test tests/Feature/Auth/TelemetryIntegrationTest.php --colors=never"
Run-TestBlock -Label "Feature/Infrastructure/Scale10xTest" -Command "php artisan test tests/Feature/Infrastructure/Scale10xTest.php --colors=never"
Run-TestBlock -Label "Feature/Infrastructure/SubdomainRoutingTest" -Command "php artisan test tests/Feature/Infrastructure/SubdomainRoutingTest.php --colors=never"

if (-not $SkipFullSuite) {
    Run-TestBlock -Label "Suite completa" -Command "php artisan test --colors=never"
}

Write-Host ""
Write-Host "OK: corrida serial finalizada sin fallos." -ForegroundColor Green
