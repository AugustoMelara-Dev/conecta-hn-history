$files = @()
Get-ChildItem app -Recurse -File -Filter "*.php" | Where-Object { $_.LastWriteTime.ToString("yyyy-MM-dd") -eq "2026-08-06" } | ForEach-Object { $files += $_.FullName }
$count = 0
foreach ($f in $files) {
    $content = Get-Content $f -Raw
    $original = $content
    $content = $content.Replace("return `$schema`r`n", "return `$form`r`n")
    $content = $content.Replace("->components([", "->schema([")
    if ($content -ne $original) {
        Set-Content $f -Value $content
        $count++
    }
}
Write-Host "Total fixes: $count"
