Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not $env:PERF_BASE_URL) {
    throw "PERF_BASE_URL is required."
}

$artifactsDir = Join-Path $PSScriptRoot "..\..\artifacts\perf"
$resolvedArtifactsDir = [System.IO.Path]::GetFullPath($artifactsDir)
[System.IO.Directory]::CreateDirectory($resolvedArtifactsDir) | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$artifactPath = Join-Path $resolvedArtifactsDir "p95-smoke-$timestamp.json"

if (-not $env:PERF_AUTH_COOKIE -and -not $env:PERF_BEARER_TOKEN) {
    if (-not $env:BUSINESS_OWNER_EMAIL -or -not $env:BUSINESS_OWNER_PASSWORD) {
        throw "Set PERF_AUTH_COOKIE or PERF_BEARER_TOKEN, or provide BUSINESS_OWNER_EMAIL and BUSINESS_OWNER_PASSWORD."
    }

    Write-Host "Fetching authenticated cookie..."
    $env:PERF_AUTH_COOKIE = (node scripts/perf/auth-cookie.mjs)
}

if (-not $env:PERF_AUTH_COOKIE -and -not $env:PERF_BEARER_TOKEN) {
    throw "Could not obtain authenticated p95 credentials."
}

if (-not $env:PERF_SAMPLES) {
    $env:PERF_SAMPLES = "100"
}

if (-not $env:PERF_CONCURRENCY) {
    $env:PERF_CONCURRENCY = "4"
}

if (-not $env:PERF_REQUIRE_AUTH) {
    $env:PERF_REQUIRE_AUTH = "true"
}

if (-not $env:PERF_FAIL_ON_ERRORS) {
    $env:PERF_FAIL_ON_ERRORS = "true"
}

Write-Host "Running authenticated p95/p99 smoke..."
node scripts/perf/p95-smoke.mjs | Tee-Object -FilePath $artifactPath
if ($LASTEXITCODE -ne 0) {
    throw "Authenticated p95/p99 smoke failed. Artifact saved to $artifactPath"
}

Write-Host "Perf artifact saved to $artifactPath"
