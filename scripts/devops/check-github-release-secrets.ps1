param(
    [string] $Repository = "VitoCode-Technologies/Vito-Business-OS"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-SecretNames {
    param(
        [string] $Scope,
        [string[]] $Arguments
    )

    $output = & gh secret list --repo $Repository @Arguments 2>$null
    if ($LASTEXITCODE -ne 0) {
        throw "Unable to list $Scope secrets for $Repository. Check GitHub CLI auth and environment access."
    }

    $names = @()
    foreach ($line in $output) {
        if (-not [string]::IsNullOrWhiteSpace($line)) {
            $names += (($line -split "\s+")[0])
        }
    }

    return $names
}

function Test-RequiredSecrets {
    param(
        [string] $Scope,
        [string[]] $Present,
        [string[]] $Required
    )

    $missing = @()
    foreach ($name in $Required) {
        if ($Present -notcontains $name) {
            $missing += $name
        }
    }

    [pscustomobject]@{
        scope = $Scope
        status = if ($missing.Count -eq 0) { "ok" } else { "failed" }
        missing = $missing
        checked = $Required
    }
}

$repoSecrets = Get-SecretNames -Scope "repository" -Arguments @()
$productionSecrets = Get-SecretNames -Scope "production environment" -Arguments @("--env", "production")
$stagingSecrets = Get-SecretNames -Scope "staging environment" -Arguments @("--env", "staging")

$productionAvailable = @($repoSecrets + $productionSecrets | Select-Object -Unique)
$stagingAvailable = @($repoSecrets + $stagingSecrets | Select-Object -Unique)

$results = @(
    Test-RequiredSecrets -Scope "production" -Present $productionAvailable -Required @(
        "APP_KEY",
        "APP_URL",
        "PROD_DB_HOST",
        "PROD_DB_PORT",
        "PROD_DB_DATABASE",
        "PROD_DB_USERNAME",
        "PROD_DB_PASSWORD",
        "PAYMENT_WEBHOOK_SECRET",
        "OPENAI_API_KEY",
        "TENANT_PRIVATE_QDRANT_URL",
        "TENANT_PRIVATE_QDRANT_API_KEY"
    )
    Test-RequiredSecrets -Scope "staging" -Present $stagingAvailable -Required @(
        "STAGING_APP_KEY",
        "STAGING_APP_URL",
        "STAGING_DB_HOST",
        "STAGING_DB_PORT",
        "STAGING_DB_DATABASE",
        "STAGING_DB_USERNAME",
        "STAGING_DB_PASSWORD",
        "STAGING_PAYMENT_WEBHOOK_SECRET",
        "STAGING_OPENAI_API_KEY",
        "STAGING_TENANT_PRIVATE_QDRANT_URL",
        "STAGING_TENANT_PRIVATE_QDRANT_API_KEY"
    )
)

$payload = [pscustomobject]@{
    status = if (($results | Where-Object { $_.status -ne "ok" }).Count -eq 0) { "ok" } else { "failed" }
    repository = $Repository
    checked_at = (Get-Date).ToUniversalTime().ToString("o")
    results = $results
}

$payload | ConvertTo-Json -Depth 5

if ($payload.status -ne "ok") {
    exit 1
}
