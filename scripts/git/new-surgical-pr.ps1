param(
    [Parameter(Mandatory = $true)]
    [string]$SliceId,
    [string]$ManifestPath = "scripts/git/surgical-pr-manifest.json",
    [switch]$NoCommit
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $ManifestPath)) {
    throw "Manifest not found: $ManifestPath"
}

$manifest = Get-Content $ManifestPath -Raw | ConvertFrom-Json
$slice = $manifest.slices | Where-Object { $_.id -eq $SliceId }
if (-not $slice) {
    throw "Slice not found: $SliceId"
}

$baseRef = $manifest.baseRef
$sourceRef = $manifest.sourceRef
$branch = $slice.branch
$fallbackBranch = $branch -replace "^codex/", "codex-pr-"
$title = $slice.title

Write-Host "Preparing slice $SliceId -> $branch"

$repoRoot = (Resolve-Path ".").Path
$tmpRoot = Join-Path $repoRoot ".tmp-pr-audit"
if (-not (Test-Path $tmpRoot)) {
    New-Item -ItemType Directory -Path $tmpRoot | Out-Null
}

$patchFile = Join-Path $tmpRoot ("{0}.patch" -f $SliceId.ToLower())
$pathsFile = Join-Path $tmpRoot ("{0}.paths.txt" -f $SliceId.ToLower())
$worktreeDir = Join-Path $tmpRoot ("wt-{0}" -f $SliceId.ToLower())

# Build file list from current worktree diff to avoid including unchanged files from glob patterns.
$allChanged = git diff --name-only $baseRef -- | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" }
$allUntracked = git ls-files --others --exclude-standard | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne "" }
if (-not $allChanged) {
    throw "No changed files detected against $baseRef."
}

function Test-PathPatternMatch {
    param(
        [string]$File,
        [string]$Pattern
    )
    if ($Pattern.Contains("*")) {
        $regex = "^" + [Regex]::Escape($Pattern).Replace("\*\*", ".*").Replace("\*", "[^\\\/]*").Replace("\\/", "[\\/]") + "$"
        return ($File -match $regex)
    }
    return $File -eq $Pattern
}

$selectedTracked = New-Object System.Collections.Generic.List[string]
$selectedUntracked = New-Object System.Collections.Generic.List[string]
foreach ($pattern in $slice.paths) {
    foreach ($file in $allChanged) {
        if (Test-PathPatternMatch -File $file -Pattern $pattern) {
            $selectedTracked.Add($file)
        }
    }
    foreach ($file in $allUntracked) {
        if (Test-PathPatternMatch -File $file -Pattern $pattern) {
            $selectedUntracked.Add($file)
        }
    }
}

$selectedTracked = $selectedTracked | Sort-Object -Unique
$selectedUntracked = $selectedUntracked | Sort-Object -Unique
$allSelected = @($selectedTracked + $selectedUntracked) | Sort-Object -Unique
if (-not $allSelected -or $allSelected.Count -eq 0) {
    throw "No matching changed files for $SliceId. Check manifest patterns."
}

$allSelected | Set-Content -Path $pathsFile

Write-Host "Generating patch: $patchFile"
if (Test-Path $patchFile) {
    Remove-Item -LiteralPath $patchFile -Force
}

if ($selectedTracked.Count -gt 0) {
    git diff --binary $baseRef -- @($selectedTracked) | Out-File -FilePath $patchFile -Encoding utf8
} else {
    New-Item -ItemType File -Path $patchFile -Force | Out-Null
}

if (Test-Path $worktreeDir) {
    git worktree remove --force "$worktreeDir" | Out-Null
}

Write-Host "Creating clean worktree from $baseRef"
try {
    git fetch --prune origin
} catch {
    Write-Warning "git fetch blocked; continuing with local refs."
}

$worktreeCreated = $false
$branchExists = $false
git show-ref --verify --quiet "refs/heads/$branch"
if ($LASTEXITCODE -eq 0) { $branchExists = $true }

if ($branchExists) {
    git worktree add "$worktreeDir" "$branch"
} else {
    git worktree add -b "$branch" "$worktreeDir" "$baseRef"
}
if ($LASTEXITCODE -eq 0) {
    $worktreeCreated = $true
} else {
    Write-Warning "Primary branch namespace failed: $branch"
    Write-Host "Retrying with fallback branch name: $fallbackBranch"
    git worktree add -b "$fallbackBranch" "$worktreeDir" "$baseRef"
    if ($LASTEXITCODE -eq 0) {
        $branch = $fallbackBranch
        $worktreeCreated = $true
    }
}

if (-not $worktreeCreated -or -not (Test-Path $worktreeDir)) {
    throw "Could not create worktree for $SliceId."
}

Push-Location $worktreeDir
try {
    foreach ($file in $allSelected) {
        $sourceFile = Join-Path $repoRoot $file
        $targetFile = Join-Path $worktreeDir $file
        if (Test-Path $sourceFile) {
            $targetParent = Split-Path -Path $targetFile -Parent
            if (-not (Test-Path $targetParent)) {
                New-Item -ItemType Directory -Path $targetParent -Force | Out-Null
            }
            Copy-Item -LiteralPath $sourceFile -Destination $targetFile -Force
            git add -- "$file"
        } elseif (Test-Path $targetFile) {
            Remove-Item -LiteralPath $targetFile -Force
            git rm -- "$file" | Out-Null
        }
    }

    if ($NoCommit) {
        Write-Host "Patch applied without commit (-NoCommit)."
    } else {
        $body = @"
Cambios:
- Slice $SliceId aplicado desde $sourceRef.
- Alcance: $($slice.scope)
- Paths: $($allSelected.Count) archivo(s).

No incluido:
- Cambios fuera del manifest del slice.
- Refactors cruzados con otros dominios.

Validacion:
- Pendiente ejecutar comandos del slice en esta rama.
"@
        git commit -m "$title" -m $body
        Write-Host "Commit created on $branch"
    }
} finally {
    Pop-Location
}

Write-Host "Done. Worktree: $worktreeDir"
Write-Host "Patch: $patchFile"
Write-Host "Paths: $pathsFile"
