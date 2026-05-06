param(
    [int]$MaxAttempts = 3,
    [int]$DelaySeconds = 2
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
    Write-Host "[migrate-retry] attempt $attempt/$MaxAttempts"

    & php artisan migrate:fresh --env=testing --force --no-interaction
    $exitCode = if ($null -ne $LASTEXITCODE) { [int]$LASTEXITCODE } else { 1 }

    if ($exitCode -eq 0) {
        Write-Host "[migrate-retry] PASS on attempt $attempt"
        exit 0
    }

    if ($attempt -lt $MaxAttempts) {
        Start-Sleep -Seconds $DelaySeconds
    }
}

Write-Host "[migrate-retry] failed after $MaxAttempts attempts"
exit 1
