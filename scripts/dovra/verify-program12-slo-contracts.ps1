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

$observabilityContract = Get-Content -Raw $observabilityContractPath | ConvertFrom-Json
$queueContract = Get-Content -Raw $queueContractPath | ConvertFrom-Json
$recoveryContract = Get-Content -Raw $recoveryContractPath | ConvertFrom-Json

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$harnessProved = $false
$harnessPayload = $null

if ($null -eq $phpCommand) {
    $results.Add((New-Result 'operational-contract-harness' 'failed' 'PHP executable is unavailable; executable trace and queue proof cannot run.'))
} else {
    $harnessLines = @(& $phpCommand.Source $harnessPath '--json' 2>&1 | ForEach-Object { $_.ToString() })
    $harnessExitCode = $LASTEXITCODE
    $harnessOutput = $harnessLines -join [Environment]::NewLine

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
        $harnessProved = $true
        $harnessDetails = 'Executable trace redaction and queue state-machine simulations proved: {0} events, {1} queues.' -f $harnessPayload.trace.event_count, $harnessPayload.queue.queue_count
        $results.Add((New-Result 'operational-contract-harness' 'proved' $harnessDetails))
    } else {
        $results.Add((New-Result 'operational-contract-harness' 'failed' 'Executable Program 12 harness did not return proved trace and queue simulations.'))
    }
}

if ($harnessProved -and $harnessPayload.trace.redaction.status -eq 'proved') {
    $results.Add((New-Result 'observability-redaction-contract' 'proved' 'Owned deterministic harness proved trace redaction without requiring shared runtime files.'))
} else {
    $results.Add((New-Result 'observability-redaction-contract' 'failed' 'Owned deterministic harness did not prove trace redaction.'))
}

function New-SharedSeamResult {
    param(
        [string]$Name,
        [object[]]$Seams
    )

    $paths = [System.Collections.Generic.List[string]]::new()
    foreach ($seam in $Seams) {
        foreach ($path in ([string]$seam.path -split '\s+\+\s+')) {
            if ($path.Trim() -ne '') {
                $paths.Add($path.Trim())
            }
        }
    }

    $missingPaths = @($paths | Where-Object {
        -not (Test-Path -LiteralPath (Join-Path $repoRoot $_))
    })

    if ($missingPaths.Count -gt 0) {
        return New-Result $Name 'external' ("Shared seam paths are absent from this owned-only checkout: {0}. Runtime owner evidence is external." -f ($missingPaths -join ', '))
    }

    return New-Result $Name 'incomplete' 'Shared seam paths may exist in the surrounding checkout, but they are outside the Task 7 owned artifacts and are not read or proved here.'
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

$missingQueueContracts = @($queueClasses | Where-Object { -not (@($queueContract.queue_classes.PSObject.Properties.Name) -contains $_) })
if ($missingQueueContracts.Count -eq 0) {
    $results.Add((New-Result 'queue-contract-manifest' 'proved' 'All Program 12 queue classes are defined in the repository contract.'))
} else {
    $results.Add((New-Result 'queue-contract-manifest' 'failed' ("Missing queue contracts: {0}" -f ($missingQueueContracts -join ', '))))
}

$observabilitySeams = @($observabilityContract.shared_runtime_seams.PSObject.Properties | ForEach-Object { $_.Value })
$queueSeams = @($queueContract.shared_runtime_seams.PSObject.Properties | ForEach-Object { $_.Value })
$results.Add((New-SharedSeamResult 'shared-observability-runtime' $observabilitySeams))
$results.Add((New-SharedSeamResult 'shared-runtime-topology' $queueSeams))

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
        'scripts/dovra/program12-operational-contract-harness.php'
    )
    note = 'Only owned artifacts are required. Shared runtime seams are classified as incomplete when present outside this slice or external when absent; managed AWS/PITR/RPO/RTO remains external.'
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
