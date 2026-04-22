param(
    [switch]$FailFast = $true,
    [string]$OutputRoot = "artifacts/audit-foundation"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outputDir = Join-Path $OutputRoot $timestamp
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

$reportFile = Join-Path $outputDir "matrix.md"
$logFile = Join-Path $outputDir "run.log"

function Write-Log {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format "u"), $Message
    $line | Tee-Object -FilePath $logFile -Append
}

function Invoke-DomainCheck {
    param(
        [string]$Domain,
        [string]$Command
    )

    Write-Log "START $Domain :: $Command"
    & powershell -NoProfile -Command $Command 2>&1 | Tee-Object -FilePath $logFile -Append
    $exitCode = $LASTEXITCODE
    if ($null -eq $exitCode) {
        $exitCode = 0
    }

    if ($exitCode -eq 0) {
        Write-Log "PASS  $Domain"
        return @{ Domain = $Domain; Status = "pass"; ExitCode = 0; Command = $Command }
    }

    Write-Log "FAIL  $Domain (exit=$exitCode)"
    if ($FailFast) {
        throw "Check failed for '$Domain' with exit code $exitCode"
    }

    return @{ Domain = $Domain; Status = "fail"; ExitCode = $exitCode; Command = $Command }
}

$checks = @(
    @{ Domain = "Migrations Governance"; Command = "composer check:migrations" },
    @{ Domain = "RBAC Governance"; Command = "composer check:rbac-governance" },
    @{ Domain = "Directory Guide Contract (stable x3)"; Command = "composer check:directory-guide-contract:stable" },
    @{ Domain = "Smoke Auth E2E"; Command = "npm run test:e2e:pr-smoke-auth -- --project=desktop-chrome" }
)

$results = @()

foreach ($check in $checks) {
    $results += Invoke-DomainCheck -Domain $check.Domain -Command $check.Command
}

$rows = $results | ForEach-Object {
    "| {0} | {1} | {2} |" -f $_.Domain, $_.Status, $_.ExitCode
}

$content = @(
    "# Audit Foundation Matrix",
    "",
    "- Generated at: $(Get-Date -Format "u")",
    "- Output directory: `$outputDir`",
    "",
    "| Domain | Status | Exit Code |",
    "| --- | --- | --- |"
) + $rows + @(
    "",
    "## Notes",
    "- `pass` means command returned `0`.",
    "- If `-FailFast` is enabled, execution stops on first failure."
)

$content | Set-Content -Path $reportFile -Encoding utf8

Write-Log "Matrix report generated at $reportFile"
Write-Output "Report: $reportFile"
