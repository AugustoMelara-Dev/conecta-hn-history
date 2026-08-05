$path = "C:\Projects\conecta-hn - copia\app\Http\Middleware\SecurityHeaders.php"
$content = Get-Content $path -Raw
$old = '            "frame-ancestors ' + [char]39 + 'none",' + "`n" +
       '            "form-action ' + [char]39 + 'self",' + "`n" +
       '            "base-uri ' + [char]39 + 'self",' + "`n" +
       '            "object-src ' + [char]39 + 'none",' + "`n" +
       '        ];'
$new = '            "frame-ancestors ' + [char]39 + 'none",' + "`n" +
       '            "form-action ' + [char]39 + 'self",' + "`n" +
       '            "base-uri ' + [char]39 + 'self",' + "`n" +
       '            "object-src ' + [char]39 + 'none",' + "`n" +
       '            ' + [char]36 + 'isProduction ? "require-trusted-types-for ' + [char]39 + 'script", "trusted-types dovra-policy ' + [char]39 + 'allow-duplicates"' + ' : "trusted-types dovra-policy ' + [char]39 + 'allow-duplicates",' + "`n" +
       '        ];'
if ($content.Contains($old)) {
    $content = $content.Replace($old, $new)
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
    Write-Host "Updated"
} else {
    Write-Host "Not found"
}
