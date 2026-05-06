param(
    [Parameter(Mandatory = $true)]
    [string]$Runner,
    [Parameter(Mandatory = $true)]
    [ValidateSet("PASS", "FAIL")]
    [string]$Result,
    [Parameter(Mandatory = $true)]
    [string]$PlaywrightSummary,
    [Parameter(Mandatory = $true)]
    [string]$ArtifactPathOrUrl
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$statusFile = "docs/qa/critical-readiness-final-status-2026-05-04.md"
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz"

$block = @"

## External evidence update ($timestamp)

- Runner: $Runner
- Command: `npm.cmd run test:e2e:release:critical:host -- -Project desktop-chrome -SkipBuild`
- Result: $Result
- Playwright summary: $PlaywrightSummary
- Artifact/log: $ArtifactPathOrUrl
"@

if ($Result -eq "PASS") {
    $block += @"
- Decision: CR-072 closed externally.
- State upgrade: READY_FOR_REVIEW.
"@
} else {
    $block += @"
- Decision: CR-072 still blocked externally.
- State remains: PARTIAL_BLOCKED.
"@
}

Add-Content -Path $statusFile -Value $block -Encoding UTF8

Write-Host "CR-072 evidence recorded in $statusFile"
