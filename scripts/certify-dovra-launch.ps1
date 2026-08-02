param(
    [string]$ExpectedHead,
    [string]$PhpBin = $env:PHP_BIN
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($PhpBin)) {
    $PhpBin = 'php'
}

if ([string]::IsNullOrWhiteSpace($ExpectedHead)) {
    $ExpectedHead = (git rev-parse HEAD).Trim()
}

$scriptPath = Join-Path $PSScriptRoot 'certify-dovra-launch.php'
$rootPath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

& $PhpBin $scriptPath "--root=$rootPath" "--expected-head=$ExpectedHead"
exit $LASTEXITCODE
