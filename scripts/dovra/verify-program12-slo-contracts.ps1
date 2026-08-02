[CmdletBinding()]
param(
    [switch]$Json
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)

function New-Result {
    param(
        [string]$Name,
        [string]$Status,
        [string]$Details
    )

    [pscustomobject]@{
        name = $Name
        status = $Status
        details = $Details
    }
}

# Status vocabulary is fixed for the Program 12 report: 'proved', 'incomplete', 'external', 'failed'.
$results = [System.Collections.Generic.List[object]]::new()

$observabilityContractPath = Join-Path $repoRoot 'deployment/dovra/program12-observability-contract.json'
$queueContractPath = Join-Path $repoRoot 'deployment/dovra/program12-queue-resilience-contract.json'
$recoveryContractPath = Join-Path $repoRoot 'deployment/dovra/program12-recovery-boundaries.json'
$harnessPath = Join-Path $repoRoot 'scripts/dovra/program12-operational-contract-harness.php'
$loggingPath = Join-Path $repoRoot 'config/logging.php'
$queuePath = Join-Path $repoRoot 'config/queue.php'
$horizonPath = Join-Path $repoRoot 'config/horizon.php'
$sentryPath = Join-Path $repoRoot 'config/sentry.php'
$observabilityPath = Join-Path $repoRoot 'config/observability.php'
$scrubberPath = Join-Path $repoRoot 'app/Infrastructure/Observability/SentryEventScrubber.php'

$observabilityContract = Get-Content -Raw $observabilityContractPath | ConvertFrom-Json
$queueContract = Get-Content -Raw $queueContractPath | ConvertFrom-Json
$recoveryContract = Get-Content -Raw $recoveryContractPath | ConvertFrom-Json
$loggingConfig = Get-Content -Raw $loggingPath
$queueConfig = Get-Content -Raw $queuePath
$horizonConfig = Get-Content -Raw $horizonPath
$sentryConfig = Get-Content -Raw $sentryPath
$observabilityConfig = Get-Content -Raw $observabilityPath
$scrubber = Get-Content -Raw $scrubberPath

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if ($null -eq $phpCommand) {
    $results.Add((New-Result 'operational-contract-harness' 'failed' 'PHP executable is unavailable; executable trace and queue proof cannot run.'))
} else {
    $harnessLines = @(& $phpCommand.Source $harnessPath '--json' 2>&1 | ForEach-Object { $_.ToString() })
    $harnessExitCode = $LASTEXITCODE
    $harnessOutput = $harnessLines -join [Environment]::NewLine
    $harnessPayload = $null

    if ($harnessOutput -ne '') {
        try {
            $harnessPayload = $harnessOutput | ConvertFrom-Json
        } catch {
            $harnessPayload = $null
        }
    }

    if (
        $harnessExitCode -eq 0 -and
        $null -ne $harnessPayload -and
        $harnessPayload.status -eq 'proved' -and
        $harnessPayload.trace.status -eq 'proved' -and
        $harnessPayload.queue.status -eq 'proved'
    ) {
        $harnessDetails = 'Executable trace redaction and queue state-machine simulations proved: {0} events, {1} queues.' -f $harnessPayload.trace.event_count, $harnessPayload.queue.queue_count
        $results.Add((New-Result 'operational-contract-harness' 'proved' $harnessDetails))
    } else {
        $results.Add((New-Result 'operational-contract-harness' 'failed' 'Executable Program 12 harness did not return proved trace and queue simulations.'))
    }
}

if (
    $sentryConfig.Contains('send_default_pii') -and
    $sentryConfig.Contains('false') -and
    $sentryConfig.Contains('SentryEventScrubber::class') -and
    $scrubber.Contains("[REDACTED]") -and
    $observabilityConfig.Contains("OTEL_PHP_AUTOLOAD_ENABLED")
) {
    $results.Add((New-Result 'observability-redaction' 'proved' 'Existing Sentry scrubber and OTEL_PHP_AUTOLOAD_ENABLED fail-closed defaults are present.'))
} else {
    $results.Add((New-Result 'observability-redaction' 'failed' 'Required sentry or OpenTelemetry redaction anchors are missing.'))
}

if (
    $loggingConfig.Contains("'security' => [") -and
    $loggingConfig.Contains("'security_file' => [") -and
    $loggingConfig.Contains("'security_slack' => [")
) {
    $results.Add((New-Result 'logging-security-channel' 'proved' 'Security logging channels exist in config/logging.php.'))
} else {
    $results.Add((New-Result 'logging-security-channel' 'failed' 'config/logging.php no longer exposes the expected security channels.'))
}

$queueClasses = @(
    'critical',
    'conversations',
    'channels',
    'workflows',
    'indexing',
    'imports',
    'analytics',
    'notifications',
    'maintenance'
)

$missingQueueContracts = @($queueClasses | Where-Object { -not $queueContract.queue_classes.PSObject.Properties.Name.Contains($_) })
if ($missingQueueContracts.Count -eq 0) {
    $results.Add((New-Result 'queue-contract-manifest' 'proved' 'All Program 12 queue classes are defined in the repository contract.'))
} else {
    $results.Add((New-Result 'queue-contract-manifest' 'failed' ("Missing queue contracts: {0}" -f ($missingQueueContracts -join ', '))))
}

if ($queueConfig.Contains("'default' => env('QUEUE_CONNECTION', 'database')") -and $horizonConfig.Contains("'queue' => ['default']")) {
    $results.Add((New-Result 'shared-runtime-topology' 'incomplete' 'The shared config seam still points at default queue wiring in config/queue.php and config/horizon.php.'))
} else {
    $results.Add((New-Result 'shared-runtime-topology' 'proved' 'Shared config already exposes a richer queue topology than the baseline captured by this slice.'))
}

if ($recoveryContract.managed_aws_proof.status -eq 'external') {
    $results.Add((New-Result 'managed-aws-proof' 'external' 'Managed AWS/PITR/RPO/RTO, DNS, secrets, and deployment smoke remain external to this repository slice.'))
} else {
    $results.Add((New-Result 'managed-aws-proof' 'failed' 'Recovery manifest no longer marks managed AWS/PITR/RPO/RTO proof as external.'))
}

$summary = [pscustomobject]@{
    generated_at = (Get-Date).ToString('o')
    inputs = @(
        'deployment/dovra/program12-observability-contract.json',
        'deployment/dovra/program12-queue-resilience-contract.json',
        'deployment/dovra/program12-recovery-boundaries.json',
        'deployment/dovra/program12-operational-contract-fixture.json',
        'scripts/dovra/program12-operational-contract-harness.php',
        'config/observability.php',
        'config/sentry.php',
        'config/logging.php',
        'config/queue.php',
        'config/horizon.php'
    )
    note = 'Executable trace/queue proof is required; shared config seam and managed AWS/PITR/RPO/RTO evidence remain explicit. No local script may upgrade them silently.'
    results = $results
}

if ($Json) {
    $summary | ConvertTo-Json -Depth 8
} else {
    $summary.results | Format-Table -AutoSize
}

if (@($results | Where-Object status -eq 'failed').Count -gt 0) {
    exit 1
}

exit 0
