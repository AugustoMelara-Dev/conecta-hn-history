Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not $env:PERF_BASE_URL) {
    throw "PERF_BASE_URL is required."
}

if (-not $env:BUSINESS_OWNER_EMAIL -or -not $env:BUSINESS_OWNER_PASSWORD) {
    throw "BUSINESS_OWNER_EMAIL and BUSINESS_OWNER_PASSWORD are required."
}

$artifactsDir = Join-Path $PSScriptRoot "..\..\artifacts\perf"
$resolvedArtifactsDir = [System.IO.Path]::GetFullPath($artifactsDir)
[System.IO.Directory]::CreateDirectory($resolvedArtifactsDir) | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$artifactPath = Join-Path $resolvedArtifactsDir "p95-smoke-$timestamp.json"

Write-Host "Fetching authenticated cookie..."
$env:PERF_AUTH_COOKIE = (node scripts/perf/auth-cookie.mjs)

if (-not $env:PERF_AUTH_COOKIE) {
    throw "Could not obtain PERF_AUTH_COOKIE."
}

if (-not $env:PERF_SAMPLES) {
    $env:PERF_SAMPLES = "100"
}

if (-not $env:PERF_CONCURRENCY) {
    $env:PERF_CONCURRENCY = "4"
}

Write-Host "Running authenticated p95/p99 smoke..."
node scripts/perf/p95-smoke.mjs | Tee-Object -FilePath $artifactPath

Write-Host "Perf artifact saved to $artifactPath"
