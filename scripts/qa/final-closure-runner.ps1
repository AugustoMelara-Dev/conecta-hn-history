param(
    [string]$BaseUrl = "",
    [switch]$SkipSchema,
    [switch]$SkipE2E,
    [switch]$RunManualAudit,
    [switch]$RunGuideChecks,
    [switch]$RequireHybrid,
    [string]$GuideCity = "san-pedro-sula",
    [string]$GuideCategory = "restaurantes",
    [string]$GuideSearch = "menu",
    [int]$GuideBudget = 100
)

$ErrorActionPreference = "Stop"

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outDir = Join-Path "artifacts/closure" $stamp
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
$log = Join-Path $outDir "runner.log"
$summary = Join-Path $outDir "summary.md"

function Write-Log {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format o), $Message
    $line | Tee-Object -FilePath $log -Append | Out-Host
}

function Invoke-Step {
    param(
        [string]$Name,
        [string]$Command
    )

    Write-Log "START :: $Name"
    Write-Log "CMD   :: $Command"
    try {
        Invoke-Expression "$Command 2>&1" | Tee-Object -FilePath $log -Append | Out-Host
        Write-Log "PASS  :: $Name"
    } catch {
        Write-Log "FAIL  :: $Name"
        throw
    }
}

Write-Log "Final closure runner started"

$executedSteps = New-Object System.Collections.Generic.List[string]

if (-not $SkipSchema) {
    $schemaCommands = @(
        "composer check:migrations",
        "composer check:schema-key-type-governance",
        "composer check:schema-fk-governance",
        "composer check:schema-nullability-governance",
        "composer check:schema-duplicate-columns-governance",
        "composer check:schema-json-governance",
        "composer check:schema-index-governance",
        "composer check:schema-wave2-governance",
        "composer check:schema-wave3-governance",
        "composer check:rbac-governance",
        "php artisan migrate:fresh --env=testing --force"
    )

    foreach ($cmd in $schemaCommands) {
        Invoke-Step -Name "schema::$cmd" -Command $cmd
        $executedSteps.Add("schema::$cmd") | Out-Null
    }
}

if (-not $SkipE2E) {
    $env:CI = "1"
    $e2eCommands = @(
        "npm run test:e2e:pr-smoke-auth -- --project=desktop-chrome",
        "npm run test:e2e:extended-nightly -- --project=desktop-chrome",
        "npm run test:e2e:release:critical -- --project=desktop-chrome"
    )

    foreach ($cmd in $e2eCommands) {
        Invoke-Step -Name "e2e::$cmd" -Command $cmd
        $executedSteps.Add("e2e::$cmd") | Out-Null
    }
}

if ($RunManualAudit) {
    if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
        throw "RunManualAudit requires -BaseUrl"
    }

    $env:PLAYWRIGHT_BASE_URL = $BaseUrl
    Invoke-Step -Name "manual-audit::$BaseUrl" -Command "node scripts/qa/manual-production-audit.mjs"
    $executedSteps.Add("manual-audit::$BaseUrl") | Out-Null
}

if ($RunGuideChecks) {
    if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
        throw "RunGuideChecks requires -BaseUrl"
    }

    $budgetUri = "{0}/api/v1/directory/guide?budget={1}&city={2}&category={3}&search={4}" -f `
        $BaseUrl.TrimEnd('/'), $GuideBudget, $GuideCity, $GuideCategory, $GuideSearch
    $fallbackUri = "{0}/api/v1/directory/guide?city={1}&category={2}&search={3}" -f `
        $BaseUrl.TrimEnd('/'), $GuideCity, $GuideCategory, $GuideSearch

    Write-Log "GET :: $budgetUri"
    $budgetResult = Invoke-RestMethod -Uri $budgetUri -Method Get -TimeoutSec 25
    $budgetMode = [string]$budgetResult.meta.ranking_mode
    Write-Log "GUIDE budget ranking_mode=$budgetMode"

    if ($RequireHybrid -and $budgetMode -ne "hybrid") {
        throw "Expected ranking_mode=hybrid for budget query, got '$budgetMode'"
    }

    Write-Log "GET :: $fallbackUri"
    $fallbackResult = Invoke-RestMethod -Uri $fallbackUri -Method Get -TimeoutSec 25
    $fallbackMode = [string]$fallbackResult.meta.ranking_mode
    $fallbackDegraded = [bool]$fallbackResult.meta.degraded_to_search
    Write-Log "GUIDE fallback ranking_mode=$fallbackMode degraded_to_search=$fallbackDegraded"

    if ($fallbackMode -ne "fallback" -or -not $fallbackDegraded) {
        throw "Fallback guide contract failed: expected ranking_mode=fallback and degraded_to_search=true"
    }

    $budgetResult | ConvertTo-Json -Depth 10 | Set-Content -Path (Join-Path $outDir "guide-budget.json") -Encoding UTF8
    $fallbackResult | ConvertTo-Json -Depth 10 | Set-Content -Path (Join-Path $outDir "guide-fallback.json") -Encoding UTF8
    $executedSteps.Add("guide-checks::$BaseUrl") | Out-Null
}

$summaryLines = @(
    "# Final Closure Runner Summary",
    "",
    "- Timestamp: $(Get-Date -Format o)",
    "- Workspace: $(Get-Location)",
    "- Output dir: $outDir",
    "- Steps executed: $($executedSteps.Count)",
    "",
    "## Steps",
    ""
)

foreach ($step in $executedSteps) {
    $summaryLines += "- $step"
}

$summaryLines += ""
$summaryLines += "- Status: PASS"
$summaryLines | Set-Content -Path $summary -Encoding UTF8

Write-Log "Final closure runner completed successfully"
Write-Host ""
Write-Host "Summary: $summary"
