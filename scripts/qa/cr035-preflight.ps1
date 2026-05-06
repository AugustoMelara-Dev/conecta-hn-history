param(
    [string]$EnvironmentName = "staging"
)

$ErrorActionPreference = 'Stop'

$required = @(
    'SMOKE_BASE_URL',
    'STAGING_APP_URL',
    'RAILWAY_API_TOKEN',
    'PERF_AUTH_COOKIE',
    'STRIPE_WEBHOOK_SECRET',
    'RESEND_API_KEY'
)

Write-Host "CR-035 preflight ($EnvironmentName)"
$missing = @()

foreach ($key in $required) {
    $value = [Environment]::GetEnvironmentVariable($key)
    if ([string]::IsNullOrWhiteSpace($value)) {
        Write-Host "$key=MISSING"
        $missing += $key
    } else {
        Write-Host "$key=SET"
    }
}

if ($missing.Count -gt 0) {
    Write-Error "CR-035 preflight failed. Missing keys: $($missing -join ', ')"
    exit 2
}

Write-Host "CR-035 preflight passed."
