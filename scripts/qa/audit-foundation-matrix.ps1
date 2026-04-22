param(
    [bool]$FailFast = $true,
    [string]$OutputRoot = "artifacts/audit-foundation",
    [switch]$SkipBrowserSmoke,
    [string[]]$OnlyDomain = @()
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outputDir = Join-Path $OutputRoot $timestamp
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

$reportFile = Join-Path $outputDir "matrix.md"
$jsonFile = Join-Path $outputDir "matrix.json"
$logFile = Join-Path $outputDir "run.log"

function Write-Log {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format "u"), $Message
    $line | Tee-Object -FilePath $logFile -Append | Out-Null
}

if (-not $env:COMPOSER_CACHE_DIR) {
    $workspaceComposerCache = Join-Path (Resolve-Path ".").Path ".composer-cache"
    New-Item -ItemType Directory -Force -Path $workspaceComposerCache | Out-Null
    $env:COMPOSER_CACHE_DIR = $workspaceComposerCache
}

Write-Log "Using COMPOSER_CACHE_DIR=$($env:COMPOSER_CACHE_DIR)"

function Invoke-DomainCheck {
    param(
        [string]$Domain,
        [string]$Command,
        [string]$Context
    )

    $startedAt = Get-Date
    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
    Write-Log "START $Domain :: $Command"

    try {
        & ([ScriptBlock]::Create($Command)) 2>&1 | Tee-Object -FilePath $logFile -Append | Out-Null
        $exitCode = $LASTEXITCODE
        if ($null -eq $exitCode) {
            $exitCode = 0
        }
    } catch {
        $_ | Out-String | Tee-Object -FilePath $logFile -Append | Out-Null
        $exitCode = if ($LASTEXITCODE) { [int]$LASTEXITCODE } else { 1 }
    }

    $stopwatch.Stop()
    $finishedAt = Get-Date

    if ($exitCode -eq 0) {
        Write-Log "PASS  $Domain"
        return [pscustomobject]@{
            Domain = $Domain
            Context = $Context
            Status = "pass"
            ExitCode = 0
            DurationSeconds = [Math]::Round($stopwatch.Elapsed.TotalSeconds, 2)
            StartedAtUtc = $startedAt.ToUniversalTime().ToString("u")
            FinishedAtUtc = $finishedAt.ToUniversalTime().ToString("u")
            Command = $Command
        }
    }

    Write-Log "FAIL  $Domain (exit=$exitCode)"
    if ($FailFast) {
        throw "Check failed for '$Domain' with exit code $exitCode"
    }

    return [pscustomobject]@{
        Domain = $Domain
        Context = $Context
        Status = "fail"
        ExitCode = $exitCode
        DurationSeconds = [Math]::Round($stopwatch.Elapsed.TotalSeconds, 2)
        StartedAtUtc = $startedAt.ToUniversalTime().ToString("u")
        FinishedAtUtc = $finishedAt.ToUniversalTime().ToString("u")
        Command = $Command
    }
}

$checks = @(
    @{
        Domain = "Migrations Governance"
        Context = "Governance Gate / Migrations"
        Command = "composer check:migrations"
    },
    @{
        Domain = "Schema Governance / Key Type"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-key-type-governance"
    },
    @{
        Domain = "Schema Governance / Nullability"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-nullability-governance"
    },
    @{
        Domain = "Schema Governance / Duplicate Columns"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-duplicate-columns-governance"
    },
    @{
        Domain = "Schema Governance / Foreign Keys"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-fk-governance"
    },
    @{
        Domain = "Schema Governance / JSON Columns"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-json-governance"
    },
    @{
        Domain = "Schema Governance / Indexes"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-index-governance"
    },
    @{
        Domain = "Schema Governance / Wave 2"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-wave2-governance"
    },
    @{
        Domain = "Schema Governance / Wave 3"
        Context = "Governance Gate / Migrations"
        Command = "composer check:schema-wave3-governance"
    },
    @{
        Domain = "RBAC Governance"
        Context = "Governance Gate / RBAC"
        Command = "composer check:rbac-governance"
    },
    @{
        Domain = "Directory Guide Contract (stable x3)"
        Context = "Governance Gate / Directory Guide Contract"
        Command = "composer check:directory-guide-contract:stable"
    }
)

if (-not $SkipBrowserSmoke) {
    $checks += @{
        Domain = "Smoke Auth E2E"
        Context = "Smoke Gate / Critical Routes"
        Command = "npm.cmd run test:e2e:pr-smoke-auth -- --project=desktop-chrome"
    }
}

if ($OnlyDomain.Count -gt 0) {
    $checks = @(
        $checks | Where-Object {
            $domainName = $_.Domain
            $contextName = $_.Context
            foreach ($pattern in $OnlyDomain) {
                if ($domainName -like $pattern -or $contextName -like $pattern) {
                    return $true
                }
            }

            return $false
        }
    )

    if ($checks.Count -eq 0) {
        throw "No checks matched -OnlyDomain filters: $($OnlyDomain -join ', ')"
    }
}

$results = @()

foreach ($check in $checks) {
    $results += Invoke-DomainCheck -Domain $check.Domain -Context $check.Context -Command $check.Command
}

$rows = $results | ForEach-Object {
    "| {0} | {1} | {2} | {3} | {4} |" -f $_.Domain, $_.Context, $_.Status, $_.ExitCode, $_.DurationSeconds
}

$passCount = @($results | Where-Object { $_.Status -eq "pass" }).Count
$failCount = @($results | Where-Object { $_.Status -eq "fail" }).Count

$content = @(
    "# Audit Foundation Matrix",
    "",
    "- Generated at: $(Get-Date -Format "u")",
    "- Output directory: $outputDir",
    "- Total checks: $($results.Count)",
    "- Passed: $passCount",
    "- Failed: $failCount",
    "- FailFast enabled: $FailFast",
    "- Browser smoke included: $(-not $SkipBrowserSmoke)",
    "- Domain filter: $(if ($OnlyDomain.Count -gt 0) { $OnlyDomain -join ', ' } else { 'all' })",
    "",
    "| Domain | CI Context | Status | Exit Code | Duration (s) |",
    "| --- | --- | --- | --- | --- |"
) + $rows + @(
    "",
    "## Notes",
    "- pass means command returned 0.",
    "- If FailFast is enabled, execution stops on first failure.",
    "- matrix.json contains machine-readable evidence for CI board normalization."
)

$content | Set-Content -Path $reportFile -Encoding utf8
$results | ConvertTo-Json -Depth 6 | Set-Content -Path $jsonFile -Encoding utf8

Write-Log "Matrix report generated at $reportFile"
Write-Log "Matrix json generated at $jsonFile"
Write-Output "Report: $reportFile"
Write-Output "JSON: $jsonFile"
