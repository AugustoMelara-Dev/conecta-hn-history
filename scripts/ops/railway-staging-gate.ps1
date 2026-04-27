Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not $env:RAILWAY_API_TOKEN) {
    throw "RAILWAY_API_TOKEN is required. Use a rotated token stored only in your local environment."
}

if (-not $env:RAILWAY_SERVICE) {
    $env:RAILWAY_SERVICE = "Vito-Business-OS"
}

if (-not $env:RAILWAY_ENVIRONMENT) {
    $env:RAILWAY_ENVIRONMENT = "staging"
}

if (-not $env:APP_HEALTH_URL -and $env:PERF_BASE_URL) {
    $env:APP_HEALTH_URL = ($env:PERF_BASE_URL.TrimEnd('/')) + "/up"
}

Write-Host "Running Railway staging gate for service $($env:RAILWAY_SERVICE) in environment $($env:RAILWAY_ENVIRONMENT)..."
pwsh $PSScriptRoot\railway-release.ps1
