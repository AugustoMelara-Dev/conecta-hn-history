param(
    [Parameter(Mandatory = $true)]
    [string]$PhpEntrypoint,
    [Parameter(Mandatory = $false, ValueFromRemainingArguments = $true)]
    [string[]]$PhpArgs = @()
)

$ErrorActionPreference = 'Stop'

function Get-RailwayVarValue {
    param(
        [Parameter(Mandatory = $true)]
        [object]$Variables,
        [Parameter(Mandatory = $true)]
        [string]$Name
    )

    if ($Variables -is [System.Collections.IDictionary]) {
        if ($Variables.Contains($Name)) {
            return $Variables[$Name]
        }
        return $null
    }

    $property = $Variables.PSObject.Properties[$Name]
    if ($null -ne $property) {
        return $property.Value
    }

    if ($Variables -is [System.Array]) {
        $candidate = $Variables | Where-Object { $_.name -eq $Name } | Select-Object -First 1
        if ($null -ne $candidate) {
            return $candidate.value
        }
    }

    return $null
}

if (-not (Test-Path -LiteralPath $PhpEntrypoint)) {
    throw "No existe el entrypoint PHP: $PhpEntrypoint"
}

$mysqlVarsRaw = npx.cmd -y @railway/cli variables --service "MySQL" --environment production --json
if ($LASTEXITCODE -ne 0) {
    throw "No se pudieron leer variables del servicio MySQL en Railway production."
}

$mysqlVars = $mysqlVarsRaw | ConvertFrom-Json
$mysqlPublicUrl = Get-RailwayVarValue -Variables $mysqlVars -Name "MYSQL_PUBLIC_URL"

if ([string]::IsNullOrWhiteSpace($mysqlPublicUrl)) {
    throw "MYSQL_PUBLIC_URL no esta definido en Railway/MySQL."
}

$dbUri = [System.Uri]$mysqlPublicUrl
$dbUserInfo = $dbUri.UserInfo.Split(':', 2)
$dbUser = [System.Uri]::UnescapeDataString($dbUserInfo[0])
$dbPass = if ($dbUserInfo.Length -gt 1) { [System.Uri]::UnescapeDataString($dbUserInfo[1]) } else { "" }
$dbName = $dbUri.AbsolutePath.TrimStart('/')
$dbPort = if ($dbUri.Port -gt 0) { $dbUri.Port } else { 3306 }

$env:DB_CONNECTION = "mysql"
$env:DB_HOST = $dbUri.Host
$env:DB_PORT = [string]$dbPort
$env:DB_DATABASE = $dbName
$env:DB_USERNAME = $dbUser
$env:DB_PASSWORD = $dbPass
$env:DATABASE_URL = $mysqlPublicUrl
$env:DB_URL = $mysqlPublicUrl
$env:APP_CONFIG_CACHE = "bootstrap/cache/config-runtime.php"
$env:APP_SERVICES_CACHE = "bootstrap/cache/services-runtime.php"
$env:APP_PACKAGES_CACHE = "bootstrap/cache/packages-runtime.php"
$env:APP_ROUTES_CACHE = "bootstrap/cache/routes-runtime.php"
$env:APP_EVENTS_CACHE = "bootstrap/cache/events-runtime.php"

$railwayArgs = @(
    "run",
    "-s", "Vito-Business-OS",
    "-e", "production",
    "php",
    $PhpEntrypoint
) + $PhpArgs

& npx.cmd -y @railway/cli @railwayArgs
exit $LASTEXITCODE
