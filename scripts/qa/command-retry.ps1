param(
    [Parameter(Mandatory = $true)]
    [string]$Command,
    [int]$MaxAttempts = 2,
    [int]$DelaySeconds = 2
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
    Write-Host "[command-retry] attempt $attempt/$MaxAttempts"
    Write-Host "[command-retry] cmd: $Command"

    & cmd.exe /d /c $Command
    $exitCode = if ($null -ne $LASTEXITCODE) { [int]$LASTEXITCODE } else { 1 }

    if ($exitCode -eq 0) {
        Write-Host "[command-retry] PASS on attempt $attempt"
        exit 0
    }

    if ($attempt -lt $MaxAttempts) {
        Start-Sleep -Seconds $DelaySeconds
    }
}

Write-Host "[command-retry] failed after $MaxAttempts attempts"
exit 1
