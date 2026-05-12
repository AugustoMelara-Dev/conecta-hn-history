param(
    [string]$BaseUrl = "http://127.0.0.1:8000",

    [string]$OutputPath = "docs/qa/commercial-mode-release-gate-$(Get-Date -Format 'yyyy-MM-dd').md",

    [string]$Modes = "appointments,products,consulting_leads",

    [string]$AppointmentsEvidence = "",
    [string]$ProductsEvidence = "",
    [string]$ConsultingEvidence = "",
    [string]$HybridEvidence = "",

    [string]$HybridAllowlist = "",

    [int]$UpExpectedStatus = 200,
    [int]$LoginExpectedStatus = 200,
    [int]$VerifyEmailExpectedStatus = 200
)

$ErrorActionPreference = "Stop"

function Write-Line {
    param([string]$Text)
    Add-Content -Path $OutputPath -Value $Text
}

function Test-ExpectedStatus {
    param(
        [string]$Route,
        [int]$ExpectedStatus
    )

    try {
        $response = Invoke-WebRequest -Uri "$BaseUrl$Route" -Method GET -MaximumRedirection 5 -TimeoutSec 30
        $status = [int]$response.StatusCode
        $ok = $status -eq $ExpectedStatus

        return @{
            Ok = $ok
            Status = "$status"
        }
    } catch {
        return @{
            Ok = $false
            Status = "ERROR: $($_.Exception.Message)"
        }
    }
}

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz"
$modeList = $Modes.Split(",") | ForEach-Object { $_.Trim().ToLower() } | Where-Object { $_ -ne "" } | Select-Object -Unique

if ($modeList.Count -eq 0) {
    throw "Modes cannot be empty."
}

$up = Test-ExpectedStatus -Route "/up" -ExpectedStatus $UpExpectedStatus
$login = Test-ExpectedStatus -Route "/login" -ExpectedStatus $LoginExpectedStatus
$verify = Test-ExpectedStatus -Route "/verify-email" -ExpectedStatus $VerifyEmailExpectedStatus
$httpOk = $up.Ok -and $login.Ok -and $verify.Ok

$modeChecks = @()
foreach ($mode in $modeList) {
    $evidence = switch ($mode) {
        "appointments" { $AppointmentsEvidence }
        "products" { $ProductsEvidence }
        "consulting_leads" { $ConsultingEvidence }
        "hybrid" { $HybridEvidence }
        default { "" }
    }

    $evidenceOk = -not [string]::IsNullOrWhiteSpace($evidence)
    $allowlistOk = $true
    $allowlistNote = ""

    if ($mode -eq "hybrid") {
        $allowlistOk = -not [string]::IsNullOrWhiteSpace($HybridAllowlist)
        $allowlistNote = if ($allowlistOk) { $HybridAllowlist } else { "MISSING (hybrid must stay tenant-allowlisted)" }
    }

    $modeChecks += @{
        Mode = $mode
        Evidence = if ($evidenceOk) { $evidence } else { "MISSING" }
        EvidenceOk = $evidenceOk
        Allowlist = if ($mode -eq "hybrid") { $allowlistNote } else { "N/A" }
        AllowlistOk = $allowlistOk
        Status = if ($evidenceOk -and $allowlistOk) { "PASS" } else { "FAIL" }
    }
}

$modeGateOk = ($modeChecks | Where-Object { $_.Status -eq "FAIL" }).Count -eq 0
$overallStatus = if ($httpOk -and $modeGateOk) { "PASS" } else { "FAIL" }

"# Commercial Mode Release Gate" | Set-Content -Path $OutputPath
Write-Line ""
Write-Line "- Generated at: $timestamp"
Write-Line "- Base URL: $BaseUrl"
Write-Line "- Modes under gate: $($modeList -join ', ')"
Write-Line "- Overall status: $overallStatus"
Write-Line ""
Write-Line "## Core HTTP smoke"
Write-Line "- /up: $($up.Status) (expected: $UpExpectedStatus)"
Write-Line "- /login: $($login.Status) (expected: $LoginExpectedStatus)"
Write-Line "- /verify-email: $($verify.Status) (expected: $VerifyEmailExpectedStatus)"
Write-Line "- Core HTTP smoke status: $(if ($httpOk) { 'PASS' } else { 'FAIL' })"
Write-Line ""
Write-Line "## Mode evidence gate"
foreach ($row in $modeChecks) {
    Write-Line "- $($row.Mode): $($row.Status) | evidence=$($row.Evidence) | allowlist=$($row.Allowlist)"
}
Write-Line ""
Write-Line "## Required notes"
Write-Line "- Do not enable risky features globally without smoke evidence."
Write-Line "- `hybrid` must remain disabled globally and only enabled by tenant allowlist."
Write-Line "- Keep release artifacts linked per mode (appointments/products/consulting_leads/hybrid pilot)."

Write-Host "Commercial mode gate report: $OutputPath"
if (-not $httpOk -or -not $modeGateOk) {
    exit 1
}
