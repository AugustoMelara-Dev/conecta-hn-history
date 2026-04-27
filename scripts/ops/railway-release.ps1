Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$service = if ($env:RAILWAY_SERVICE) { $env:RAILWAY_SERVICE } else { "Vito-Business-OS" }
$environment = if ($env:RAILWAY_ENVIRONMENT) { $env:RAILWAY_ENVIRONMENT } else { "production" }
$healthUrl = $env:APP_HEALTH_URL

if (-not $env:RAILWAY_API_TOKEN) {
    throw "RAILWAY_API_TOKEN is required. Use a rotated token stored only in your local environment."
}

function Invoke-Railway {
    param([Parameter(Mandatory = $true)][string[]] $Arguments)

    & npx.cmd -y '@railway/cli' @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Railway command failed: railway $($Arguments -join ' ')"
    }
}

Write-Host "Checking Railway identity..."
Invoke-Railway @("whoami")

Write-Host "Checking Railway project status..."
Invoke-Railway @("status")

Write-Host "Deploying $service to $environment..."
Invoke-Railway @("up", "--service", $service, "--environment", $environment, "--ci", "--message", "Local gated release")

$commands = @(
    "php artisan migrate --force --no-interaction",
    "php artisan optimize:clear",
    "php artisan queue:restart",
    "php artisan business-ai:rag-preflight --strict --require-qdrant",
    "php artisan business-ai:qdrant-ensure-collection --json"
)

foreach ($command in $commands) {
    Write-Host "Running remote command: $command"
    Invoke-Railway @("ssh", "--service", $service, "--environment", $environment, $command)
}

if ($healthUrl) {
    Write-Host "Checking health endpoint..."
    $response = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 30

    if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 300) {
        throw "Health check failed with HTTP $($response.StatusCode)."
    }
}

Write-Host "Railway release completed."
