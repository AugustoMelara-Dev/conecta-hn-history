Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not $env:STRIPE_WEBHOOK_FORWARD_URL) {
    throw "STRIPE_WEBHOOK_FORWARD_URL is required, for example https://staging.example.com/api/webhooks/payment"
}

if (-not $env:STRIPE_SMOKE_EVENT) {
    $env:STRIPE_SMOKE_EVENT = "payment_intent.succeeded"
}

Write-Host "Opening Stripe listener against $($env:STRIPE_WEBHOOK_FORWARD_URL)"
Write-Host "Keep this terminal open while Stripe forwards signed events."
stripe listen --forward-to $env:STRIPE_WEBHOOK_FORWARD_URL
