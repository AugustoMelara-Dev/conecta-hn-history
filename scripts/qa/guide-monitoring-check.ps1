param(
    [int]$Hours = 24,
    [switch]$FailOnAlerts,
    [string]$BaseUrl = "",
    [string]$GuideToken = "",
    [switch]$RunRouteAudit,
    [string]$GuideCity = "tegucigalpa",
    [string]$GuideCategory = "restaurantes",
    [string]$GuideSearch = "cafe",
    [int]$GuideBudget = 100
)

$ErrorActionPreference = "Stop"

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outDir = Join-Path "artifacts/monitoring" $stamp
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$logPath = Join-Path $outDir "monitor.log"
$opsJsonPath = Join-Path $outDir "directory-guide-ops-report.json"
$summaryPath = Join-Path $outDir "summary.md"

function Write-Log {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format o), $Message
    $line | Tee-Object -FilePath $logPath -Append | Out-Host
}

function Extract-JsonPayload {
    param([string]$RawText)

    $start = $RawText.IndexOf('{')
    if ($start -lt 0) {
        throw "No JSON payload found in command output."
    }

    return $RawText.Substring($start)
}

Write-Log "Directory guide monitoring check started"
Write-Log "Output: $outDir"

$opsCommand = "php artisan directory:guide:ops-report --hours=$Hours --json"
Write-Log "Running ops report command: $opsCommand"

$opsLines = Invoke-Expression "$opsCommand 2>&1"
$opsLines | Tee-Object -FilePath $logPath -Append | Out-Host

$opsExitCode = $LASTEXITCODE
if ($opsExitCode -ne 0) {
    throw "directory:guide:ops-report failed with exit code $opsExitCode"
}

$opsRaw = ($opsLines | Out-String).Trim()
$opsJson = Extract-JsonPayload -RawText $opsRaw
$opsJson | Set-Content -Path $opsJsonPath -Encoding UTF8

$opsReport = $opsJson | ConvertFrom-Json
$alerts = @($opsReport.alerts)
$alertCount = $alerts.Count

Write-Log ("Ops report parsed. requests={0}, alerts={1}" -f $opsReport.total_requests, $alertCount)

if ($FailOnAlerts -and $alertCount -gt 0) {
    throw ("Alerts detected and -FailOnAlerts enabled: {0}" -f ($alerts -join ", "))
}

$guideProbeStatus = "skipped"
$budgetPath = Join-Path $outDir "guide-budget.json"
$fallbackPath = Join-Path $outDir "guide-fallback.json"

if (-not [string]::IsNullOrWhiteSpace($BaseUrl) -or -not [string]::IsNullOrWhiteSpace($GuideToken)) {
    if ([string]::IsNullOrWhiteSpace($BaseUrl) -or [string]::IsNullOrWhiteSpace($GuideToken)) {
        throw "Guide probe requires both -BaseUrl and -GuideToken."
    }

    $headers = @{
        Authorization = "Bearer $GuideToken"
        Accept = "application/json"
    }

    $normalizedBase = $BaseUrl.TrimEnd('/')
    $budgetUri = "{0}/api/v1/directory/guide?budget={1}&city={2}&category={3}&search={4}" -f `
        $normalizedBase, $GuideBudget, $GuideCity, $GuideCategory, $GuideSearch
    $fallbackUri = "{0}/api/v1/directory/guide?city={1}&category={2}&search={3}" -f `
        $normalizedBase, $GuideCity, $GuideCategory, $GuideSearch

    Write-Log "Guide probe budget request: $budgetUri"
    $budgetResponse = Invoke-RestMethod -Uri $budgetUri -Method Get -Headers $headers -TimeoutSec 25
    $budgetResponse | ConvertTo-Json -Depth 10 | Set-Content -Path $budgetPath -Encoding UTF8

    $budgetMode = [string]$budgetResponse.meta.ranking_mode
    if (@("sql", "hybrid", "fallback") -notcontains $budgetMode) {
        throw "Unexpected ranking_mode in budget probe: '$budgetMode'"
    }

    Write-Log "Guide probe fallback request: $fallbackUri"
    $fallbackResponse = Invoke-RestMethod -Uri $fallbackUri -Method Get -Headers $headers -TimeoutSec 25
    $fallbackResponse | ConvertTo-Json -Depth 10 | Set-Content -Path $fallbackPath -Encoding UTF8

    $fallbackMode = [string]$fallbackResponse.meta.ranking_mode
    $fallbackDegraded = [bool]$fallbackResponse.meta.degraded_to_search
    if ($fallbackMode -ne "fallback" -or -not $fallbackDegraded) {
        throw "Fallback probe contract failed: expected ranking_mode=fallback and degraded_to_search=true."
    }

    Write-Log ("Guide probes OK. budget_mode={0}, fallback_mode={1}" -f $budgetMode, $fallbackMode)
    $guideProbeStatus = "passed"
}

$routeAuditStatus = "skipped"
if ($RunRouteAudit) {
    if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
        throw "Route audit requires -BaseUrl."
    }

    $env:PLAYWRIGHT_BASE_URL = $BaseUrl
    $routeCommand = "node scripts/qa/manual-production-audit.mjs"
    Write-Log "Running route audit: $routeCommand"

    Invoke-Expression "$routeCommand 2>&1" | Tee-Object -FilePath $logPath -Append | Out-Host
    $routeAuditStatus = "passed"
}

$summaryLines = @(
    "# Directory Guide Monitoring Summary",
    "",
    "- Timestamp: $(Get-Date -Format o)",
    "- Output: $outDir",
    "- Hours window: $Hours",
    "- Total requests: $($opsReport.total_requests)",
    "- 429 count: $($opsReport.status_429)",
    "- 5xx count: $($opsReport.status_5xx)",
    "- Ranking modes: hybrid=$($opsReport.ranking_modes.hybrid), sql=$($opsReport.ranking_modes.sql), fallback=$($opsReport.ranking_modes.fallback)",
    "- Degraded reasons: timeout=$($opsReport.degraded_reasons.openai_timeout), auth_failed=$($opsReport.degraded_reasons.openai_auth_failed), runtime_unavailable=$($opsReport.degraded_reasons.guide_runtime_unavailable)",
    "- Rates: quota=$($opsReport.rates.quota_exceeded), timeout=$($opsReport.rates.openai_timeout), runtime_unavailable=$($opsReport.rates.guide_runtime_unavailable)",
    "- OpenAI cost total: $($opsReport.openai_cost_total)",
    "- Alerts: $(if ($alertCount -eq 0) { 'none' } else { $alerts -join ', ' })",
    "- Guide probes: $guideProbeStatus",
    "- Route audit: $routeAuditStatus",
    "",
    "- Status: PASS"
)

$summaryLines | Set-Content -Path $summaryPath -Encoding UTF8

Write-Log "Directory guide monitoring check finished"
Write-Host ""
Write-Host "Summary: $summaryPath"
