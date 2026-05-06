param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,

    [string]$EnvironmentName = "staging",

    [string]$OutputPath = "",

    [string]$StripeEventId = "",
    [string]$ResendRequestId = "",
    [string]$PerfArtifactPath = "",

    [switch]$ValidateEvidence,
    [switch]$AllowPending
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $OutputPath = "docs/qa/critical-readiness-staging-prod-evidence-$(Get-Date -Format 'yyyy-MM-dd').md"
}

$packParams = @{
    BaseUrl = $BaseUrl
    EnvironmentName = $EnvironmentName
    OutputPath = $OutputPath
}

if (-not [string]::IsNullOrWhiteSpace($StripeEventId)) {
    $packParams.StripeEventId = $StripeEventId
}
if (-not [string]::IsNullOrWhiteSpace($ResendRequestId)) {
    $packParams.ResendRequestId = $ResendRequestId
}
if (-not [string]::IsNullOrWhiteSpace($PerfArtifactPath)) {
    $packParams.PerfArtifactPath = $PerfArtifactPath
}

Write-Host "[1/2] Generating baseline evidence pack..."
& powershell -ExecutionPolicy Bypass -File "scripts/qa/critical-readiness-staging-prod-pack.ps1" @packParams

Write-Host "[2/2] Appending runtime guards evidence..."
& powershell -ExecutionPolicy Bypass -File "scripts/qa/append-runtime-guards-evidence.ps1" `
    -EvidencePath $OutputPath `
    -EnvironmentLabel "$EnvironmentName-runtime"

if ($ValidateEvidence) {
    Write-Host "[3/3] Validating evidence pack..."
    $validatorParams = @(
        "-ExecutionPolicy", "Bypass",
        "-File", "scripts/qa/validate-cr035-evidence.ps1",
        "-EvidencePath", $OutputPath
    )

    if ($AllowPending) {
        $validatorParams += "-AllowPending"
    }

    & powershell @validatorParams
}

Write-Host "CR-035 evidence workflow completed: $OutputPath"
