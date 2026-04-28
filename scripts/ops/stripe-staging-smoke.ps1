Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not $env:STRIPE_WEBHOOK_FORWARD_URL) {
    throw "STRIPE_WEBHOOK_FORWARD_URL is required, for example https://staging.example.com/api/webhooks/payment"
}

if (-not (Get-Command stripe -ErrorAction SilentlyContinue)) {
    throw "Stripe CLI is required. Install and authenticate it with 'stripe login' before running the real signed-event smoke."
}

if (-not $env:STRIPE_SMOKE_EVENT) {
    $env:STRIPE_SMOKE_EVENT = "payment_intent.succeeded"
}

Write-Host "Opening Stripe listener against $($env:STRIPE_WEBHOOK_FORWARD_URL)"
Write-Host "In another terminal, run: stripe trigger $($env:STRIPE_SMOKE_EVENT)"
Write-Host "Keep this terminal open while Stripe forwards signed events."
stripe listen --forward-to $env:STRIPE_WEBHOOK_FORWARD_URL
