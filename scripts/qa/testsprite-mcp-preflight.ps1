param(
    [switch] $RequireApiKey,
    [switch] $CheckPackage
)

$ErrorActionPreference = 'Stop'

function Write-Status {
    param(
        [string] $Name,
        [string] $Status,
        [string] $Detail = ''
    )

    if ($Detail) {
        Write-Host ("[{0}] {1} - {2}" -f $Status, $Name, $Detail)
        return
    }

    Write-Host ("[{0}] {1}" -f $Status, $Name)
}

function Get-CommandVersion {
    param(
        [string] $Command,
        [string[]] $CommandArgs
    )

    $output = & $Command @CommandArgs 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ("{0} failed with exit code {1}: {2}" -f $Command, $LASTEXITCODE, ($output -join ' '))
    }

    return ($output | Select-Object -First 1)
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $repoRoot

Write-Host "TestSprite MCP preflight"
Write-Host ("Repo: {0}" -f $repoRoot)

$nodeVersion = Get-CommandVersion -Command 'node' -CommandArgs @('--version')
Write-Status -Name 'node available' -Status 'OK' -Detail $nodeVersion

$npmVersion = Get-CommandVersion -Command 'npm.cmd' -CommandArgs @('--version')
Write-Status -Name 'npm available' -Status 'OK' -Detail $npmVersion

$apiKey = $env:TESTSPRITE_API_KEY
if (-not $apiKey) {
    $apiKey = $env:API_KEY
}

$apiKeySource = 'shell'
if (-not $apiKey) {
    $localMcpConfig = Join-Path $repoRoot '.cursor\mcp.json'
    if (Test-Path $localMcpConfig) {
        try {
            $mcpConfig = Get-Content $localMcpConfig -Raw | ConvertFrom-Json
            $apiKey = $mcpConfig.mcpServers.TestSprite.env.API_KEY
            $apiKeySource = '.cursor/mcp.json'
        } catch {
            Write-Status -Name 'local MCP config parse' -Status 'WARN' -Detail 'could not parse .cursor/mcp.json'
        }
    }
}

if ($apiKey) {
    Write-Status -Name 'TestSprite API key' -Status 'OK' -Detail ("present in {0}, value redacted" -f $apiKeySource)
} elseif ($RequireApiKey) {
    Write-Status -Name 'TestSprite API key' -Status 'FAIL' -Detail 'set TESTSPRITE_API_KEY/API_KEY or add it to .cursor/mcp.json before running with -RequireApiKey'
    exit 1
} else {
    Write-Status -Name 'TestSprite API key' -Status 'WARN' -Detail 'not set; put the key in your local MCP client config'
}

if ($CheckPackage) {
    $versionOutput = & npm.cmd view '@testsprite/testsprite-mcp' version 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Status -Name 'TestSprite npm package lookup' -Status 'FAIL' -Detail ($versionOutput -join ' ')
        exit 1
    }

    Write-Status -Name 'TestSprite npm package lookup' -Status 'OK' -Detail ("version {0}" -f (($versionOutput | Select-Object -First 1) -as [string]))
} else {
    Write-Status -Name 'TestSprite npm package lookup' -Status 'SKIP' -Detail 'use -CheckPackage for network package validation'
}

Write-Host ''
Write-Host 'MCP config to place in the local IDE/client, with your real key:'
Write-Host @'
{
  "mcpServers": {
    "TestSprite": {
      "command": "npx",
      "args": [
        "@testsprite/testsprite-mcp@latest"
      ],
      "env": {
        "API_KEY": "your-api-key"
      }
    }
  }
}
'@

Write-Host ''
Write-Status -Name 'TestSprite MCP setup' -Status 'READY' -Detail 'see docs/qa/testsprite-mcp-setup.md'
