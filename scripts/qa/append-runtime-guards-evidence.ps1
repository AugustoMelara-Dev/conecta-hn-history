param(
    [Parameter(Mandatory = $true)]
    [string]$EvidencePath,

    [string]$EnvironmentLabel = "runtime"
)

$ErrorActionPreference = 'Continue'

if (-not (Test-Path $EvidencePath)) {
    throw "Evidence file not found: $EvidencePath"
}

function Add-Section($title) {
    Add-Content -Path $EvidencePath -Value ""
    Add-Content -Path $EvidencePath -Value "## $title"
}

function Add-CommandResult($label, $command) {
    Add-Content -Path $EvidencePath -Value "- $label"
    Add-Content -Path $EvidencePath -Value '```text'
    Add-Content -Path $EvidencePath -Value "> $command"

    try {
        $output = Invoke-Expression $command 2>&1 | Out-String
        Add-Content -Path $EvidencePath -Value $output.TrimEnd()
    } catch {
        Add-Content -Path $EvidencePath -Value "ERROR: $($_.Exception.Message)"
    }

    Add-Content -Path $EvidencePath -Value '```'
}

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz"

Add-Section "Runtime guards ($EnvironmentLabel)"
Add-Content -Path $EvidencePath -Value "- Captured at: $timestamp"

Add-CommandResult "config production guard" "php artisan config:production-guard"
Add-CommandResult "storage production guard" "php artisan storage:production-guard"

Write-Host "Runtime guards appended to: $EvidencePath"
