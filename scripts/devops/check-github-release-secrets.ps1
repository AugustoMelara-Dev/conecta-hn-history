param(
    [string] $Repository = "AugustoMelara-Dev/Vito-Business-OS"
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
        "REDIS_URL",
        "PAYMENT_WEBHOOK_SECRET",
        "SENTRY_LARAVEL_DSN",
        "VITE_SENTRY_DSN",
        "BETTER_STACK_SOURCE_TOKEN",
        "BETTER_STACK_INGESTING_HOST",
        "MAIL_FROM_ADDRESS",
        "RESEND_API_KEY",
        "RESEND_WEBHOOK_SECRET",
        "WHATSAPP_CLOUD_ACCESS_TOKEN",
        "WHATSAPP_CLOUD_PHONE_NUMBER_ID",
        "WHATSAPP_CLOUD_VERIFY_TOKEN",
        "WHATSAPP_CLOUD_APP_SECRET",
        "OPENAI_API_KEY",
        "TENANT_PRIVATE_QDRANT_URL",
        "TENANT_PRIVATE_QDRANT_API_KEY",
        "VAPID_PUBLIC_KEY",
        "VAPID_PRIVATE_KEY"
    )
    Test-RequiredSecrets -Scope "staging" -Present $stagingAvailable -Required @(
        "STAGING_APP_KEY",
        "STAGING_APP_URL",
        "STAGING_DB_HOST",
        "STAGING_DB_PORT",
        "STAGING_DB_DATABASE",
        "STAGING_DB_USERNAME",
        "STAGING_DB_PASSWORD",
        "STAGING_REDIS_URL",
        "STAGING_PAYMENT_WEBHOOK_SECRET",
        "STAGING_SENTRY_LARAVEL_DSN",
        "STAGING_VITE_SENTRY_DSN",
        "STAGING_BETTER_STACK_SOURCE_TOKEN",
        "STAGING_BETTER_STACK_INGESTING_HOST",
        "STAGING_MAIL_FROM_ADDRESS",
        "STAGING_RESEND_API_KEY",
        "STAGING_RESEND_WEBHOOK_SECRET",
        "STAGING_WHATSAPP_CLOUD_ACCESS_TOKEN",
        "STAGING_WHATSAPP_CLOUD_PHONE_NUMBER_ID",
        "STAGING_WHATSAPP_CLOUD_VERIFY_TOKEN",
        "STAGING_WHATSAPP_CLOUD_APP_SECRET",
        "STAGING_OPENAI_API_KEY",
        "STAGING_TENANT_PRIVATE_QDRANT_URL",
        "STAGING_TENANT_PRIVATE_QDRANT_API_KEY",
        "STAGING_VAPID_PUBLIC_KEY",
        "STAGING_VAPID_PRIVATE_KEY"
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
