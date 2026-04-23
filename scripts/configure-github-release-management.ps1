param(
    [string] $GitHubToken = "",

    [string] $Repository = "",

    [string] $ReleasePleaseToken = "",

    [string[]] $RequiredChecks = @(
        "CI Gate / Final Status",
        "Quality Gate / Frontend",
        "Quality Gate / Lint",
        "Quality Gate / Static Analysis",
        "Security Gate / Dependency Audit",
        "Security SAST / Final Status",
        "Security Secrets / Final Status",
        "Permissions and Webhook Governance"
    ),

    [ValidateRange(0, 6)]
    [int] $RequiredApprovingReviewCount = 0,

    [switch] $RequireLastPushApproval,

    [switch] $SkipRulesets,

    [switch] $SkipSecret,

    [switch] $DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-RepositorySlug {
    param([string] $ExplicitRepository)

    if ($ExplicitRepository) {
        return $ExplicitRepository
    }

    $remoteUrl = git remote get-url origin
    if ($remoteUrl -match 'github\.com[:/](?<owner>[^/]+)/(?<repo>[^/.]+)(?:\.git)?$') {
        return "$($Matches.owner)/$($Matches.repo)"
    }

    throw "No pude inferir el repositorio desde 'origin'. Usa -Repository owner/repo."
}

function Invoke-GitHubApi {
    param(
        [string] $Method,
        [string] $Uri,
        [object] $Body = $null
    )

    if ($DryRun) {
        Write-Host "[DRY-RUN] $Method $Uri"
        if ($null -ne $Body) {
            Write-Host ($Body | ConvertTo-Json -Depth 20)
        }
        return $null
    }

    $headers = @{
        Accept                 = "application/vnd.github+json"
        Authorization          = "Bearer $GitHubToken"
        "X-GitHub-Api-Version" = "2022-11-28"
        "User-Agent"           = "conecta-release-bootstrap"
    }

    $invokeParams = @{
        Method      = $Method
        Uri         = $Uri
        Headers     = $headers
        ContentType = "application/json"
    }

    if ($null -ne $Body) {
        $invokeParams.Body = ($Body | ConvertTo-Json -Depth 20)
    }

    Invoke-RestMethod @invokeParams
}

function New-RulesetPayload {
    param(
        [string] $Name,
        [string[]] $IncludePatterns,
        [string[]] $Checks,
        [int] $ApprovingReviewCount,
        [bool] $LastPushApprovalRequired
    )

    $rules = @(
        @{ type = "deletion" },
        @{ type = "non_fast_forward" },
        @{ type = "required_linear_history" },
        @{
            type       = "pull_request"
            parameters = @{
                allowed_merge_methods            = @("merge", "squash")
                dismiss_stale_reviews_on_push    = $true
                require_code_owner_review        = $false
                require_last_push_approval       = $LastPushApprovalRequired
                required_approving_review_count  = $ApprovingReviewCount
                required_review_thread_resolution = $true
            }
        }
    )

    if ($Checks.Count -gt 0) {
        $rules += @{
            type       = "required_status_checks"
            parameters = @{
                do_not_enforce_on_create          = $false
                strict_required_status_checks_policy = $true
                required_status_checks            = @($Checks | ForEach-Object { @{ context = $_ } })
            }
        }
    }

    return @{
        name        = $Name
        target      = "branch"
        enforcement = "active"
        conditions  = @{
            ref_name = @{
                include = $IncludePatterns
                exclude = @()
            }
        }
        rules       = $rules
    }
}

function Upsert-Ruleset {
    param(
        [string] $Owner,
        [string] $Repo,
        [hashtable] $Payload
    )

    if ($DryRun) {
        Write-Host "[DRY-RUN] Would upsert ruleset '$($Payload.name)' in $Owner/$Repo"
        return
    }

    $existingRulesets = Invoke-GitHubApi -Method "GET" -Uri "https://api.github.com/repos/$Owner/$Repo/rulesets"
    $existing = $existingRulesets | Where-Object { $_.name -eq $Payload.name } | Select-Object -First 1

    if ($existing) {
        Invoke-GitHubApi -Method "PUT" -Uri "https://api.github.com/repos/$Owner/$Repo/rulesets/$($existing.id)" -Body $Payload | Out-Null
        Write-Host "Updated ruleset: $($Payload.name)"
        return
    }

    Invoke-GitHubApi -Method "POST" -Uri "https://api.github.com/repos/$Owner/$Repo/rulesets" -Body $Payload | Out-Null
    Write-Host "Created ruleset: $($Payload.name)"
}

function Set-ReleasePleaseSecret {
    param(
        [string] $Owner,
        [string] $Repo,
        [string] $TokenValue
    )

    if ($DryRun) {
        Write-Host "[DRY-RUN] Would set RELEASE_PLEASE_TOKEN secret for $Owner/$Repo (if provided)."
        return
    }

    $ghCommand = Get-Command gh -ErrorAction SilentlyContinue
    if (-not $ghCommand) {
        Write-Warning "No encontre GitHub CLI. El workflow funcionara con GITHUB_TOKEN, pero para setear RELEASE_PLEASE_TOKEN instala gh y ejecuta:"
        Write-Warning "  gh secret set RELEASE_PLEASE_TOKEN -R $Owner/$Repo"
        return
    }

    if (-not $TokenValue) {
        Write-Warning "No se proporciono -ReleasePleaseToken. Omitiendo secret RELEASE_PLEASE_TOKEN."
        return
    }

    $TokenValue | & $ghCommand.Source secret set RELEASE_PLEASE_TOKEN -R "$Owner/$Repo"
    Write-Host "Secret actualizado: RELEASE_PLEASE_TOKEN"
}

$repositorySlug = Get-RepositorySlug -ExplicitRepository $Repository
$parts = $repositorySlug.Split("/")

if ($parts.Count -ne 2) {
    throw "El repositorio debe tener formato owner/repo."
}

$owner = $parts[0]
$repo = $parts[1]

Write-Host "Configuring GitHub release management for $owner/$repo"
Write-Host ("Required checks: {0}" -f ($RequiredChecks -join ", "))
Write-Host ("Review approvals required: {0}" -f $RequiredApprovingReviewCount)
Write-Host ("Require last push approval: {0}" -f ([bool] $RequireLastPushApproval))
Write-Host ("Dry run: {0}" -f $DryRun)

if (-not $DryRun -and -not $GitHubToken) {
    throw "GitHub token requerido. Usa -GitHubToken o ejecuta con -DryRun para validar sin escribir cambios."
}

if (-not $SkipRulesets) {
    Upsert-Ruleset -Owner $owner -Repo $repo -Payload (New-RulesetPayload -Name "Protect main" -IncludePatterns @("refs/heads/main") -Checks $RequiredChecks -ApprovingReviewCount $RequiredApprovingReviewCount -LastPushApprovalRequired ([bool] $RequireLastPushApproval))
    Upsert-Ruleset -Owner $owner -Repo $repo -Payload (New-RulesetPayload -Name "Protect release branches" -IncludePatterns @("refs/heads/release/*") -Checks $RequiredChecks -ApprovingReviewCount $RequiredApprovingReviewCount -LastPushApprovalRequired ([bool] $RequireLastPushApproval))
    Upsert-Ruleset -Owner $owner -Repo $repo -Payload (New-RulesetPayload -Name "Protect hotfix branches" -IncludePatterns @("refs/heads/hotfix/*") -Checks $RequiredChecks -ApprovingReviewCount $RequiredApprovingReviewCount -LastPushApprovalRequired ([bool] $RequireLastPushApproval))
}

if (-not $SkipSecret) {
    Set-ReleasePleaseSecret -Owner $owner -Repo $repo -TokenValue $ReleasePleaseToken
}

Write-Host "Done."
