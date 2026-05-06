param(
    [string]$BaseUrl = "",
    [string]$EnvironmentName = "staging",
    [string]$StripeEventId = "",
    [string]$ResendRequestId = "",
    [string]$PerfArtifactPath = "",
    [string]$OutputPath = ""
)

$ErrorActionPreference = 'Stop'

Write-Host "[CR-035] Step 1/2: Preflight gate..."
powershell -ExecutionPolicy Bypass -File "scripts/qa/cr035-preflight.ps1" -EnvironmentName $EnvironmentName
if ($LASTEXITCODE -ne 0) {
    throw "CR-035 preflight gate failed. Aborting strict close workflow."
}

if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    $BaseUrl = [Environment]::GetEnvironmentVariable('SMOKE_BASE_URL')
}

if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    throw "CR-035 strict close requires -BaseUrl or SMOKE_BASE_URL."
}

Write-Host "[CR-035] Step 2/2: Evidence workflow (strict validation)..."
powershell -ExecutionPolicy Bypass -File "scripts/qa/run-cr035-evidence-workflow.ps1" `
    -BaseUrl $BaseUrl `
    -EnvironmentName $EnvironmentName `
    -OutputPath $OutputPath `
    -StripeEventId $StripeEventId `
    -ResendRequestId $ResendRequestId `
    -PerfArtifactPath $PerfArtifactPath `
    -ValidateEvidence
if ($LASTEXITCODE -ne 0) {
    throw "CR-035 evidence workflow failed."
}

Write-Host "CR-035 strict close workflow finished."
