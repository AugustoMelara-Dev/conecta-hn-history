$files = @()
Get-ChildItem app -Recurse -File -Filter "*.php" | Where-Object { $_.LastWriteTime.ToString("yyyy-MM-dd") -eq "2026-08-06" } | ForEach-Object { $files += $_.FullName }
$count = 0
foreach ($f in $files) {
    $content = Get-Content $f -Raw
    $original = $content
    # Fix `return $schema\r\n` -> `return $form\r\n`
    $content = $content.Replace("return `$schema`r`n", "return `$form`r`n")
    $content = $content.Replace("return `$schema`n", "return `$form`n")
    if ($content -ne $original) {
        Set-Content $f -Value $content
        $count++
    }
}
Write-Host "Fixed: $count"
