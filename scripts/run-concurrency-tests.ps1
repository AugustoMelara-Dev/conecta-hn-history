# CONCURRENCY TEST EXECUTION SCRIPT (PowerShell)
# Usage: .\scripts\run-concurrency-tests.ps1
# Purpose: Spin up MySQL, run SKIP LOCKED tests, tear down

param(
    [switch]$KeepContainer = $false,
    [string]$TestFilter = "OutboxScalingTest"
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "  CONCURRENCY TEST SUITE - MySQL 8.0 Ephemeral Environment" -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan

# PHASE 1: SPIN UP MYSQL CONTAINER
Write-Host "`n[1/5] Starting MySQL 8.0 container..." -ForegroundColor Yellow

Set-Location $ProjectRoot
docker compose -f docker-compose.testing.yml up -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Failed to start MySQL container" -ForegroundColor Red
    exit 1
}

# PHASE 2: WAIT FOR HEALTHY STATUS
Write-Host "[2/5] Waiting for MySQL to be healthy..." -ForegroundColor Yellow

$maxAttempts = 30
$attempt = 0
$health = ""

do {
    $attempt++
    try {
        $health = docker inspect --format="{{.State.Health.Status}}" conecta_mysql_testing 2>$null
    }
    catch {
        $health = "starting"
    }
    
    if ($health -eq "healthy") {
        Write-Host "  OK - MySQL is healthy!" -ForegroundColor Green
        break
    }
    
    Write-Host "  Attempt $attempt/$maxAttempts - Status: $health" -ForegroundColor Gray
    Start-Sleep -Seconds 2
} while ($attempt -lt $maxAttempts)

if ($health -ne "healthy") {
    Write-Host "ERROR: MySQL failed to become healthy after $maxAttempts attempts" -ForegroundColor Red
    docker compose -f docker-compose.testing.yml down -v
    exit 1
}

# PHASE 3: RUN MIGRATIONS
Write-Host "[3/5] Running migrations on testing database..." -ForegroundColor Yellow

$env:DB_CONNECTION = "mysql"
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "33060"
$env:DB_DATABASE = "testing_concurrency"
$env:DB_USERNAME = "root"
$env:DB_PASSWORD = "secret"

php artisan migrate:fresh --force

if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Migrations failed" -ForegroundColor Red
    docker compose -f docker-compose.testing.yml down -v
    exit 1
}

Write-Host "  OK - Migrations complete!" -ForegroundColor Green

# PHASE 4: EXECUTE CONCURRENCY TESTS
Write-Host "[4/5] Running concurrency tests..." -ForegroundColor Yellow
Write-Host "  Filter: $TestFilter" -ForegroundColor Gray

php artisan test --filter=$TestFilter

$testResult = $LASTEXITCODE

if ($testResult -eq 0) {
    Write-Host "`n  OK - ALL TESTS PASSED!" -ForegroundColor Green
}
else {
    Write-Host "`n  FAIL - TESTS FAILED" -ForegroundColor Red
}

# PHASE 5: CLEANUP
if (-not $KeepContainer) {
    Write-Host "[5/5] Destroying ephemeral container..." -ForegroundColor Yellow
    docker compose -f docker-compose.testing.yml down -v
    Write-Host "  OK - Container destroyed" -ForegroundColor Green
}
else {
    Write-Host "[5/5] Keeping container (use: docker compose -f docker-compose.testing.yml down -v)" -ForegroundColor Yellow
}

# Clear environment variables
Remove-Item Env:\DB_CONNECTION -ErrorAction SilentlyContinue
Remove-Item Env:\DB_HOST -ErrorAction SilentlyContinue
Remove-Item Env:\DB_PORT -ErrorAction SilentlyContinue
Remove-Item Env:\DB_DATABASE -ErrorAction SilentlyContinue
Remove-Item Env:\DB_USERNAME -ErrorAction SilentlyContinue
Remove-Item Env:\DB_PASSWORD -ErrorAction SilentlyContinue

Write-Host "`n=================================================================" -ForegroundColor Cyan
Write-Host "  TEST EXECUTION COMPLETE" -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan

exit $testResult
