param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,

    [string]$OutputPath = "docs/qa/critical-readiness-staging-prod-evidence-$(Get-Date -Format 'yyyy-MM-dd').md",

    [string]$EnvironmentName = "staging",

    [string]$StripeEventId = "",
    [string]$ResendRequestId = "",
    [string]$PerfArtifactPath = "",

    [int]$UpExpectedStatus = 200,
    [int]$LoginExpectedStatus = 200,
    [int]$VerifyEmailExpectedStatus = 200
)

$ErrorActionPreference = 'Stop'

function Write-Line {
    param([string]$Text)
    Add-Content -Path $OutputPath -Value $Text
}

function Is-HttpStatusOk {
    param(
        [string]$Value,
        [int]$Expected
    )

    if ($Value -match '^[0-9]+$') {
        return ([int]$Value -eq $Expected)
    }

    return $false
}

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz"

$upStatus = "N/A"
$loginStatus = "N/A"
$verifyStatus = "N/A"

try {
    $up = Invoke-WebRequest -Uri "$BaseUrl/up" -Method GET -MaximumRedirection 5 -TimeoutSec 30
    $upStatus = "$($up.StatusCode)"
} catch {
    $upStatus = "ERROR: $($_.Exception.Message)"
}

try {
    $login = Invoke-WebRequest -Uri "$BaseUrl/login" -Method GET -MaximumRedirection 5 -TimeoutSec 30
    $loginStatus = "$($login.StatusCode)"
} catch {
    $loginStatus = "ERROR: $($_.Exception.Message)"
}

try {
    $verify = Invoke-WebRequest -Uri "$BaseUrl/verify-email" -Method GET -MaximumRedirection 5 -TimeoutSec 30
    $verifyStatus = "$($verify.StatusCode)"
} catch {
    $verifyStatus = "ERROR: $($_.Exception.Message)"
}

$upOk = Is-HttpStatusOk -Value $upStatus -Expected $UpExpectedStatus
$loginOk = Is-HttpStatusOk -Value $loginStatus -Expected $LoginExpectedStatus
$verifyOk = Is-HttpStatusOk -Value $verifyStatus -Expected $VerifyEmailExpectedStatus

$providerIdsPresent = -not [string]::IsNullOrWhiteSpace($StripeEventId) -and `
    -not [string]::IsNullOrWhiteSpace($ResendRequestId) -and `
    -not [string]::IsNullOrWhiteSpace($PerfArtifactPath)

$httpSmokeStatus = if ($upOk -and $loginOk -and $verifyOk) { 'PASS' } else { 'FAIL' }
$providerEvidenceStatus = if ($providerIdsPresent) { 'PRESENT' } else { 'PENDING' }

$overallStatus = 'OPEN'
if ($httpSmokeStatus -eq 'PASS' -and $providerEvidenceStatus -eq 'PRESENT') {
    $overallStatus = 'CLOSE_READY (requires manual provider/runtime confirmation checkboxes)'
}

"# Critical Readiness Staging/Prod Evidence Pack" | Set-Content -Path $OutputPath
Write-Line ""
Write-Line "- Generated at: $timestamp"
Write-Line "- Environment: $EnvironmentName"
Write-Line "- Base URL: $BaseUrl"
Write-Line ""
Write-Line "## HTTP smoke"
Write-Line "- /up: $upStatus (expected: $UpExpectedStatus)"
Write-Line "- /login: $loginStatus (expected: $LoginExpectedStatus)"
Write-Line "- /verify-email: $verifyStatus (expected: $VerifyEmailExpectedStatus)"
Write-Line "- HTTP smoke status: $httpSmokeStatus"
Write-Line ""
Write-Line "## Provider evidence"
Write-Line "- Stripe signed event id: $(if ([string]::IsNullOrWhiteSpace($StripeEventId)) {'PENDING'} else {$StripeEventId})"
Write-Line "- Resend request id: $(if ([string]::IsNullOrWhiteSpace($ResendRequestId)) {'PENDING'} else {$ResendRequestId})"
Write-Line "- Authenticated p95 artifact: $(if ([string]::IsNullOrWhiteSpace($PerfArtifactPath)) {'PENDING'} else {$PerfArtifactPath})"
Write-Line "- Provider evidence status: $providerEvidenceStatus"
Write-Line ""
Write-Line "## Required manual confirmations"
Write-Line "- [ ] Stripe event was provider-signed (not synthetic local trigger)."
Write-Line "- [ ] Resend OTP/email reached real inbox and correlation id was stored."
Write-Line "- [ ] p95 run used authenticated flow and artifact is attached."
Write-Line "- [ ] `config:production-guard` and `storage:production-guard` were executed inside target runtime."
Write-Line ""
Write-Line "## Notes"
Write-Line "- This pack is additive and does not expose secrets."
Write-Line "- Keep provider request IDs and links to logs/CI artifacts in this file."
Write-Line "- Overall CR-035 status from this run: $overallStatus"

Write-Host "Evidence pack created at: $OutputPath"
