$failures = @()
Get-Content architecture_failures.log | ForEach-Object {
    if ($_ -match "^\s*(FAIL|FAILED|ERROR|⨯)\s+(.*)") {
        $failures += $Matches[2].Trim()
    }
}
$failures = $failures | Select-Object -Unique
$failures | Set-Content clean_arch_failures.txt
Write-Output "Architecture Falures Count: $($failures.Count)"
