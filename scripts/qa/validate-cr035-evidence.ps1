param(
    [Parameter(Mandatory = $true)]
    [string]$EvidencePath,

    [switch]$AllowPending
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $EvidencePath)) {
    throw "Evidence file not found: $EvidencePath"
}

$content = Get-Content -Raw -Path $EvidencePath

$requiredMarkers = @(
    '/up:',
    '/login:',
    '/verify-email:',
    'Stripe signed event id:',
    'Resend request id:',
    'Authenticated p95 artifact:',
    'config:production-guard --strict',
    'storage:production-guard --strict'
)

$missing = @()
foreach ($marker in $requiredMarkers) {
    if ($content -notmatch [regex]::Escape($marker)) {
        $missing += $marker
    }
}

if ($missing.Count -gt 0) {
    Write-Host "CR-035 evidence validation: FAIL"
    Write-Host "Missing required markers:"
    $missing | ForEach-Object { Write-Host "- $_" }
    exit 1
}

if (-not $AllowPending) {
    $pending = @()
    if ($content -match 'Stripe signed event id:\s*PENDING') { $pending += 'Stripe signed event id' }
    if ($content -match 'Resend request id:\s*PENDING') { $pending += 'Resend request id' }
    if ($content -match 'Authenticated p95 artifact:\s*PENDING') { $pending += 'Authenticated p95 artifact' }

    if ($pending.Count -gt 0) {
        Write-Host "CR-035 evidence validation: FAIL"
        Write-Host "Pending provider evidence:"
        $pending | ForEach-Object { Write-Host "- $_" }
        exit 2
    }
}

Write-Host "CR-035 evidence validation: PASS"
exit 0
