param(
    [string]$OutputRoot = "artifacts/critical-readiness-ultralight"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outputDir = Join-Path $OutputRoot $timestamp
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

$logFile = Join-Path $outputDir "run.log"
$summaryFile = Join-Path $outputDir "summary.md"

function Write-Log {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format "u"), $Message
    $line | Tee-Object -FilePath $logFile -Append | Out-Host
}

function Run-Step {
    param(
        [string]$Name,
        [string]$Command
    )

    Write-Log "START $Name"
    Write-Log "CMD   $Command"

    $tempFile = [System.IO.Path]::GetTempFileName()
    try {
        & cmd.exe /d /c "$Command > `"$tempFile`" 2>&1"
        $exitCode = if ($null -ne $LASTEXITCODE) { [int]$LASTEXITCODE } else { 0 }
        $output = @(Get-Content -Path $tempFile -ErrorAction SilentlyContinue)
        $output | Tee-Object -FilePath $logFile -Append | Out-Host
        return [pscustomobject]@{
            Name = $Name
            Command = $Command
            ExitCode = $exitCode
            Status = if ($exitCode -eq 0) { "pass" } else { "fail" }
        }
    } finally {
        Remove-Item -Path $tempFile -Force -ErrorAction SilentlyContinue
    }
}

$steps = @(
    @{ Name = "Config cache"; Command = "php artisan config:cache" },
    @{ Name = "Launch routes present"; Command = "php artisan route:list | rg ""\b(up|login|verify-email)\b"" -n" },
    @{ Name = "Launch routes test"; Command = "php artisan test --filter=LaunchRoutesAvailabilityTest --colors=never" },
    @{ Name = "Exception fallback contract"; Command = "php artisan test --filter=ExceptionFallbackContractTest --colors=never" },
    @{ Name = "Onboarding E2E contract"; Command = "php artisan test --filter=OnboardingE2EContractTest --colors=never" },
    @{ Name = "Product claims contract"; Command = "php artisan test --filter=ProductClaimsContractTest --colors=never" },
    @{ Name = "Env example guard"; Command = "php artisan test --filter=EnvExampleSecurityTest --colors=never" },
    @{ Name = "UI guardrails"; Command = "npm.cmd run check:ui-guardrails" }
)

$results = @()
foreach ($step in $steps) {
    $results += Run-Step -Name $step.Name -Command $step.Command
}

$fails = @($results | Where-Object { $_.Status -eq "fail" }).Count

$summary = @(
    "# Critical Readiness Ultralight",
    "",
    "- Timestamp: $timestamp",
    "- Failures: $fails",
    "",
    "| Step | Status | Exit code |",
    "| --- | --- | --- |"
)

foreach ($r in $results) {
    $summary += "| $($r.Name) | $($r.Status) | $($r.ExitCode) |"
}

$summary += ""
$summary += "Log: $logFile"

$summary | Set-Content -Path $summaryFile -Encoding UTF8
Write-Log "Summary written to $summaryFile"

if ($fails -gt 0) {
    exit 1
}

exit 0

