param(
    [string]$OutputRoot = "artifacts/vertical-scorecard"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outputDir = Join-Path $OutputRoot $timestamp
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

$jsonFile = Join-Path $outputDir "scorecard.json"
$summaryFile = Join-Path $outputDir "summary.md"

$json = php artisan tenant:vertical-scorecard --json
$json | Set-Content -Path $jsonFile -Encoding utf8

$data = $json | ConvertFrom-Json

$lines = @(
    "# Vertical Capability Scorecard",
    "",
    "- Generated at: $(Get-Date -Format "u")",
    "",
    "| Vertical | Ready | Partial | Missing | Total |",
    "| --- | --- | --- | --- | --- |"
)

$rows = @()

if ($null -ne $data) {
    if ($data -is [System.Collections.IEnumerable] -and -not ($data -is [string])) {
        foreach ($item in $data) {
            $rows += $item
        }
    } elseif ($null -ne $data.summary) {
        foreach ($item in $data.summary) {
            $rows += $item
        }
    }
}

if ($rows.Count -eq 0) {
    $lines += "| (no data) | 0 | 0 | 0 | 0 |"
} else {
    foreach ($item in $rows) {
        $vertical = if ($null -ne $item.vertical) { $item.vertical } elseif ($null -ne $item.segment) { $item.segment } else { "unknown" }
        $ready = if ($null -ne $item.ready) { $item.ready } else { 0 }
        $partial = if ($null -ne $item.partial) { $item.partial } else { 0 }
        $missing = if ($null -ne $item.missing) { $item.missing } else { 0 }
        $total = if ($null -ne $item.total) { $item.total } else { 0 }
        $lines += "| $vertical | $ready | $partial | $missing | $total |"
    }
}

$lines += ""
$lines += "Raw output: $jsonFile"

$lines | Set-Content -Path $summaryFile -Encoding utf8

Write-Output "Artifacts generated in: $outputDir"
