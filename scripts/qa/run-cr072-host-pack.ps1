param(
    [switch]$SkipBrowser,
    [switch]$FailOnBlockedBrowser,
    [switch]$OnlyBrowser,
    [switch]$AllowBlockedBrowser
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Write-Host "== CR-072 host execution pack =="
Write-Host "This script must run from a host shell (not restricted sandbox)."

$releaseCmd = "powershell -ExecutionPolicy Bypass -File scripts/qa/critical-readiness-gates.ps1 -Only release"
$browserCmd = "powershell -ExecutionPolicy Bypass -File scripts/qa/critical-readiness-gates.ps1 -Only browser"

function Get-LatestCriticalReadinessArtifact {
    $root = Join-Path $PSScriptRoot "..\..\artifacts\critical-readiness"
    if (-not (Test-Path $root)) {
        return $null
    }

    return Get-ChildItem -Path $root -Directory |
        Sort-Object LastWriteTimeUtc -Descending |
        Select-Object -First 1
}

function Run-BrowserFallbackSmoke {
    Write-Host "`n[Fallback] Running HTTP smoke against local app surface (/up, /login, /verify-email)..."

    $targets = @(
        "http://127.0.0.1:8000/up",
        "http://127.0.0.1:8000/login",
        "http://127.0.0.1:8000/verify-email"
    )

    $allOk = $true

    foreach ($url in $targets) {
        try {
            $response = Invoke-WebRequest -Uri $url -Method GET -TimeoutSec 12 -UseBasicParsing
            $code = [int]$response.StatusCode
            if ($code -ge 500) {
                $allOk = $false
                Write-Host "FAIL fallback smoke: $url returned HTTP $code"
            } else {
                Write-Host "OK fallback smoke: $url returned HTTP $code"
            }
        } catch {
            $allOk = $false
            Write-Host "FAIL fallback smoke: $url unreachable ($($_.Exception.Message))"
        }
    }

    return $allOk
}

function Start-TemporaryLocalServer {
    $phpExecutable = "php"
    $serverHost = "127.0.0.1"
    $port = 8000
    $artisanPath = Join-Path $PSScriptRoot "..\..\artisan"
    $workingDir = Resolve-Path (Join-Path $PSScriptRoot "..\..")

    if (-not (Test-Path $artisanPath)) {
        return $null
    }

    try {
        $probe = Invoke-WebRequest -Uri "http://$serverHost`:$port/up" -Method GET -TimeoutSec 2 -UseBasicParsing
        if ([int]$probe.StatusCode -lt 500) {
            Write-Host "[Fallback] Detected existing local server on ${serverHost}:${port}."
            return @{
                Process = $null
                Started = $false
            }
        }
    } catch {
        # No local server detected; continue.
    }

    Write-Host "[Fallback] Starting temporary local server on ${serverHost}:${port} ..."
    $serverProcess = Start-Process -FilePath $phpExecutable `
        -ArgumentList "artisan", "serve", "--host=$serverHost", "--port=$port" `
        -WorkingDirectory $workingDir `
        -WindowStyle Hidden `
        -PassThru

    $ready = $false
    for ($i = 0; $i -lt 15; $i++) {
        Start-Sleep -Milliseconds 500
        try {
            $probe = Invoke-WebRequest -Uri "http://$serverHost`:$port/up" -Method GET -TimeoutSec 2 -UseBasicParsing
            if ([int]$probe.StatusCode -lt 500) {
                $ready = $true
                break
            }
        } catch {
            # wait and retry
        }
    }

    if (-not $ready) {
        if ($null -ne $serverProcess -and -not $serverProcess.HasExited) {
            Stop-Process -Id $serverProcess.Id -Force
        }
        Write-Host "[Fallback] Temporary local server did not become ready."
        return $null
    }

    Write-Host "[Fallback] Temporary local server ready (PID $($serverProcess.Id))."
    return @{
        Process = $serverProcess
        Started = $true
    }
}

if (-not $OnlyBrowser) {
    Write-Host "`n[1/2] Running release gates..."
    cmd /d /c $releaseCmd
    $releaseExit = $LASTEXITCODE
} else {
    $releaseExit = 0
    Write-Host "`n[1/2] Release gates skipped by -OnlyBrowser."
}

if ($releaseExit -ne 0) {
    Write-Host "`nRelease gates finished with non-zero exit code: $releaseExit"
    Write-Host "Check artifacts/critical-readiness/<timestamp>/summary.md and run.log"
}

if (-not $SkipBrowser) {
    Write-Host "`n[2/2] Running browser gates..."
    cmd /d /c $browserCmd
    $browserExit = $LASTEXITCODE

    if ($browserExit -ne 0) {
        Write-Host "`nBrowser gates finished with non-zero exit code: $browserExit"
        Write-Host "Check artifacts/critical-readiness/<timestamp>/summary.md and run.log"

        $latest = Get-LatestCriticalReadinessArtifact
        if ($null -ne $latest) {
            $summaryPath = Join-Path $latest.FullName "summary.md"
            $logPath = Join-Path $latest.FullName "run.log"
            $blockedBrowser = $false
            $spawnEperm = $false

            if (Test-Path $summaryPath) {
                $summary = Get-Content $summaryPath -Raw
                $blockedBrowser = $summary -match "Browser release-critical.*blocked"
            }

            if (Test-Path $logPath) {
                $log = Get-Content $logPath -Raw
                $spawnEperm = $log -match "spawn EPERM"
            }

            if ($releaseExit -eq 0 -and $blockedBrowser -and $spawnEperm -and -not $FailOnBlockedBrowser) {
                Write-Host "`nCR-072 host pack: PARTIAL_BLOCKED (known Playwright spawn EPERM on this runner)."
                Write-Host "Evidence: $($latest.FullName)"

                $tempServer = Start-TemporaryLocalServer
                try {
                    $fallbackOk = Run-BrowserFallbackSmoke
                    if ($fallbackOk) {
                        Write-Host "Fallback HTTP smoke: PASS"
                    } else {
                        Write-Host "Fallback HTTP smoke: FAIL"
                    }
                } finally {
                    if ($null -ne $tempServer -and $tempServer.Started -and $null -ne $tempServer.Process -and -not $tempServer.Process.HasExited) {
                        Stop-Process -Id $tempServer.Process.Id -Force
                        Write-Host "[Fallback] Temporary local server stopped."
                    }
                }

                if ($AllowBlockedBrowser) {
                    Write-Host "AllowBlockedBrowser enabled: continuing as non-fatal blocked browser state."
                    exit 0
                }

                exit 2
            }
        }
    }
} else {
    $browserExit = 0
    Write-Host "`n[2/2] Browser gates skipped by flag."
}

if ($releaseExit -eq 0 -and $browserExit -eq 0) {
    if (-not $SkipBrowser) {
        $latest = Get-LatestCriticalReadinessArtifact
        if ($null -ne $latest) {
            $summaryPath = Join-Path $latest.FullName "summary.md"
            if (Test-Path $summaryPath) {
                $summary = Get-Content $summaryPath -Raw
                $blockedBrowser = $summary -match "Browser PR auth smoke.*blocked" -or
                    $summary -match "Browser release-critical.*blocked"

                if ($blockedBrowser) {
                    Write-Host "`nCR-072 host pack: PARTIAL_BLOCKED (browser gates blocked)."
                    Write-Host "Evidence: $($latest.FullName)"

                    $tempServer = Start-TemporaryLocalServer
                    try {
                        $fallbackOk = Run-BrowserFallbackSmoke
                        if ($fallbackOk) {
                            Write-Host "Fallback HTTP smoke: PASS"
                        } else {
                            Write-Host "Fallback HTTP smoke: FAIL"
                        }
                    } finally {
                        if ($null -ne $tempServer -and $tempServer.Started -and $null -ne $tempServer.Process -and -not $tempServer.Process.HasExited) {
                            Stop-Process -Id $tempServer.Process.Id -Force
                            Write-Host "[Fallback] Temporary local server stopped."
                        }
                    }

                    if ($FailOnBlockedBrowser) {
                        exit 1
                    }

                    if ($AllowBlockedBrowser) {
                        Write-Host "AllowBlockedBrowser enabled: continuing as non-fatal blocked browser state."
                        exit 0
                    }

                    exit 2
                }
            }
        }
    }

    Write-Host "`nCR-072 host pack: PASS"
    exit 0
}

Write-Host "`nCR-072 host pack: PARTIAL_BLOCKED or FAIL (see artifacts)"
exit 1
