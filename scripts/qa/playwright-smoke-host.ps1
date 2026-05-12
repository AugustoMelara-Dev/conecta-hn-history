param(
    [string]$Spec = "",
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $root

if (-not $SkipBuild) {
    Write-Host "[playwright-smoke-host] Building frontend..."
    & npm.cmd run build
}

$startedServer = $false
$serverProcess = $null
$serverLog = Join-Path $env:TEMP "playwright-smoke-host-laravel.log"
$serverErrLog = Join-Path $env:TEMP "playwright-smoke-host-laravel.err.log"
try {
    $health = $null
    try {
        $health = Invoke-WebRequest -Uri "http://localhost:8000/up" -UseBasicParsing -TimeoutSec 3
    } catch {}

    if (-not $health -or $health.StatusCode -ge 500) {
        Write-Host "[playwright-smoke-host] Starting Laravel server on http://localhost:8000 ..."
        if (Test-Path $serverLog) { Remove-Item $serverLog -Force -ErrorAction SilentlyContinue }
        if (Test-Path $serverErrLog) { Remove-Item $serverErrLog -Force -ErrorAction SilentlyContinue }
        $serverProcess = Start-Process -FilePath "php" -ArgumentList @("artisan", "serve", "--host", "127.0.0.1", "--port", "8000") -WorkingDirectory $root -PassThru -WindowStyle Hidden -RedirectStandardOutput $serverLog -RedirectStandardError $serverErrLog
        $startedServer = $true

        $ready = $false
        for ($i = 0; $i -lt 40; $i++) {
            Start-Sleep -Milliseconds 500
            try {
                $probe = Invoke-WebRequest -Uri "http://localhost:8000/up" -UseBasicParsing -TimeoutSec 3
                if ($probe.StatusCode -lt 500) {
                    $ready = $true
                    break
                }
            } catch {}
        }

        if (-not $ready) {
            $logTail = ""
            if (Test-Path $serverLog) {
                $logTail = (Get-Content $serverLog -Tail 60 -ErrorAction SilentlyContinue) -join "`n"
            }
            if (Test-Path $serverErrLog) {
                $errTail = (Get-Content $serverErrLog -Tail 60 -ErrorAction SilentlyContinue) -join "`n"
                $logTail = "$logTail`n$errTail"
            }
            throw "Laravel server did not become ready at http://localhost:8000`n$logTail"
        }
    } else {
        Write-Host "[playwright-smoke-host] Reusing existing server at http://localhost:8000"
    }

    $args = @("playwright", "test")
    if ($Spec -and $Spec.Trim() -ne "") {
        $splitSpecs = $Spec.Trim().Split(" ", [System.StringSplitOptions]::RemoveEmptyEntries)
        $args += $splitSpecs
    }

    $args += @("--reporter=list,html,junit")

    Write-Host "[playwright-smoke-host] Running: npx.cmd $($args -join ' ')"
    & npx.cmd @args
} finally {
    if ($startedServer -and $serverProcess -and -not $serverProcess.HasExited) {
        Write-Host "[playwright-smoke-host] Stopping Laravel server (PID $($serverProcess.Id))"
        Stop-Process -Id $serverProcess.Id -Force -ErrorAction SilentlyContinue
    }
}
