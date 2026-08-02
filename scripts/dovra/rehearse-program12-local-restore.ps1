[CmdletBinding()]
param(
    [string]$FixturePath = '',
    [switch]$Json
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)

if ([string]::IsNullOrWhiteSpace($FixturePath)) {
    $FixturePath = Join-Path $repoRoot 'deployment/dovra/program12-local-restore-fixture.json'
}

$fixture = Get-Content -Raw $FixturePath | ConvertFrom-Json
$startedAt = Get-Date

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("dovra-program12-restore-{0}" -f ([guid]::NewGuid().ToString('N')))
$sourceDir = Join-Path $tempRoot 'source'
$restoreDir = Join-Path $tempRoot 'isolated-restore'

New-Item -ItemType Directory -Path $sourceDir -Force | Out-Null
New-Item -ItemType Directory -Path $restoreDir -Force | Out-Null

# Create a synthetic source snapshot and restore it into an isolated restore directory.
$sourceSnapshotPath = Join-Path $sourceDir 'program12-snapshot.json'
$restoreSnapshotPath = Join-Path $restoreDir 'program12-snapshot.json'

$fixture | ConvertTo-Json -Depth 20 | Set-Content -Path $sourceSnapshotPath -Encoding UTF8
$sourceHash = (Get-FileHash -Path $sourceSnapshotPath -Algorithm SHA256).Hash
Copy-Item -LiteralPath $sourceSnapshotPath -Destination $restoreSnapshotPath -Force
$restoreHash = (Get-FileHash -Path $restoreSnapshotPath -Algorithm SHA256).Hash
$restored = Get-Content -Raw $restoreSnapshotPath | ConvertFrom-Json

$tenantCount = @($restored.snapshot.tenants).Count
$artifactCount = @($restored.snapshot.artifacts).Count
$durationMs = [math]::Round(((Get-Date) - $startedAt).TotalMilliseconds, 2)

$result = [pscustomobject]@{
    status = if ($sourceHash -eq $restoreHash -and $tenantCount -ge 2 -and $artifactCount -ge 2) { 'proved' } else { 'failed' }
    scope = 'repository-controlled artifact rehearsal'
    fixture = 'deployment/dovra/program12-local-restore-fixture.json'
    source_hash = $sourceHash
    restored_hash = $restoreHash
    tenant_count = $tenantCount
    artifact_count = $artifactCount
    isolated_restore_directory = $restoreDir
    duration_ms = $durationMs
    note = 'Synthetic fixture rehearsal only; managed AWS proof remains external.'
}

if ($Json) {
    $result | ConvertTo-Json -Depth 6
} else {
    $result | Format-List
}

if ($result.status -ne 'proved') {
    exit 1
}

exit 0
