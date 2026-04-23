param(
    [Parameter(Mandatory = $true)]
    [string] $Repository,

    [Parameter(Mandatory = $true)]
    [string] $SecretsFile
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $SecretsFile)) {
    throw "Secrets file not found: $SecretsFile"
}

$raw = Get-Content -LiteralPath $SecretsFile -Raw
$payload = $raw | ConvertFrom-Json

foreach ($environment in @('Preview', 'Production')) {
    $envBlock = $payload.PSObject.Properties[$environment]
    if ($null -eq $envBlock) {
        Write-Host "Skipping ${environment}: no block found in JSON."
        continue
    }

    $envSecrets = $envBlock.Value
    foreach ($entry in $envSecrets.PSObject.Properties) {
        $name = [string] $entry.Name
        $value = [string] $entry.Value
        if ([string]::IsNullOrWhiteSpace($value)) {
            Write-Host "Skipping ${environment}/${name}: empty value."
            continue
        }

        Write-Host "Setting $environment/$name"
        $value | gh secret set $name --repo $Repository --env $environment
    }
}

Write-Host 'Environment secrets sync completed.'
