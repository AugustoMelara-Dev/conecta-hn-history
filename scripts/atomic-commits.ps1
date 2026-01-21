# ============================================================================
# ATOMIC COMMITS SCRIPT - Conventional Commits Standard
# ============================================================================
# Run from project root: .\scripts\atomic-commits.ps1
# ============================================================================

$ErrorActionPreference = "Continue"

function Safe-Commit {
    param([string]$Message, [string[]]$Files)
    
    foreach ($file in $Files) {
        if (Test-Path $file) {
            git add $file
        }
        else {
            Write-Warning "File not found: $file"
        }
    }
    
    $result = git commit -m $Message 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  [OK] $Message" -ForegroundColor Green
    }
    else {
        Write-Warning "  [SKIP] $Message - No changes to commit"
    }
}

function Safe-Delete-Commit {
    param([string]$Message, [string[]]$Files)
    
    foreach ($file in $Files) {
        if (Test-Path $file) {
            Remove-Item $file -Force
            git add $file
        }
    }
    git add -u
    
    $result = git commit -m $Message 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  [OK] $Message" -ForegroundColor Green
    }
    else {
        Write-Warning "  [SKIP] $Message - No changes"
    }
}

# ============================================================================
# PHASE 0: BIOHAZARD CLEANUP - DELETE DEBUG FILES
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Red
Write-Host "PHASE 0: BIOHAZARD CLEANUP" -ForegroundColor Red
Write-Host "========================================`n" -ForegroundColor Red

$debugFiles = @(
    "debug_binding.php",
    "debug_event.txt",
    "debug_final.txt",
    "debug_log.txt",
    "debug_log_2.txt",
    "debug_output.txt",
    "debug_user.txt",
    "migrate_debug.txt",
    "migrate_final.txt",
    "migrate_fresh_log.txt",
    "migrate_fresh_log2.txt",
    "migrate_log.txt",
    "storage/debug_success.txt"
)

foreach ($file in $debugFiles) {
    if (Test-Path $file) {
        Remove-Item $file -Force
        Write-Host "  [DELETED] $file" -ForegroundColor Yellow
    }
}

Write-Host "`n  Debug files purged.`n" -ForegroundColor Green

# ============================================================================
# CONTEXT 1: Core Domain Contracts
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 1: Core Domain Contracts" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Domain/Shared/Contracts/*.php 2>$null
git commit -m "feat(shared): add domain contracts for EventDispatcher and Idempotency"
if ($?) { Write-Host "  [OK] feat(shared): add domain contracts" -ForegroundColor Green }

git add app/Domain/Shared/Exceptions/*.php 2>$null
git commit -m "feat(shared): add domain exceptions"
if ($?) { Write-Host "  [OK] feat(shared): add domain exceptions" -ForegroundColor Green }

git add app/Domain/Shared/ValueObjects/TenantId.php 2>$null
git commit -m "refactor(shared): update TenantId value object logic"
if ($?) { Write-Host "  [OK] refactor(shared): update TenantId" -ForegroundColor Green }

if (Test-Path "app/ValueObjects/TenantId.php") {
    git rm app/ValueObjects/TenantId.php 2>$null
    git commit -m "chore(shared): remove deprecated legacy TenantId"
    if ($?) { Write-Host "  [OK] chore(shared): remove deprecated TenantId" -ForegroundColor Green }
}

# ============================================================================
# CONTEXT 2: Auth Domain Hardening
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 2: Auth Domain Hardening" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Domain/Auth/Events/UserPasswordChanged.php 2>$null
git commit -m "feat(auth): add UserPasswordChanged domain event"

git add app/Domain/Auth/Exceptions/*.php 2>$null
git commit -m "feat(auth): add MaxOtpAttemptsExceededException"

git add app/Domain/Auth/Entities/OtpEntity.php 2>$null
git commit -m "refactor(auth): update OtpEntity for atomic operations compliance"

git add app/Domain/Auth/Repositories/OtpRepositoryInterface.php 2>$null
git commit -m "refactor(auth): enforce strict types in OtpRepositoryInterface"

git add app/Domain/Auth/Contracts/OtpNotifierInterface.php 2>$null
git commit -m "refactor(auth): update OtpNotifierInterface contract"

git add app/Domain/Auth/Events/OtpGenerated.php 2>$null
git commit -m "refactor(auth): standardize OtpGenerated event structure"

# ============================================================================
# CONTEXT 3: Auth Application Layer Decorators
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 3: Auth Application Decorators" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Application/Auth/Handlers/*Interface.php 2>$null
git commit -m "feat(auth): add application handler interfaces"

git add app/Application/Auth/Validators/ 2>$null
git commit -m "feat(auth): add auth validators"

git add app/Application/Auth/Decorators/RateLimitedGenerateOtpHandler.php 2>$null
git add app/Application/Auth/Decorators/VerifyOtpRateLimitDecorator.php 2>$null
git commit -m "feat(auth): add rate-limited OTP decorators"

git add app/Application/Auth/Decorators/TransactionGenerateOtpHandler.php 2>$null
git commit -m "feat(auth): add transactional OTP decorator"

git add app/Application/Auth/Decorators/ValidationGenerateOtpHandler.php 2>$null
git commit -m "feat(auth): add validation OTP decorator"

git add app/Application/Auth/Decorators/AtomicIdempotencyDecorator.php 2>$null
git add app/Application/Auth/Decorators/DeleteUserAtomicDecorator.php 2>$null
git add app/Application/Auth/Decorators/DeleteUserValidationDecorator.php 2>$null
git add app/Application/Auth/Decorators/LoginViaSocialProviderAtomicDecorator.php 2>$null
git add app/Application/Auth/Decorators/LoginViaSocialProviderValidationDecorator.php 2>$null
git commit -m "feat(auth): add atomic idempotency decorators"

# ============================================================================
# CONTEXT 4: Auth Commands & Handlers Updates
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 4: Auth Commands & Handlers" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Application/Auth/Commands/DeleteUserCommand.php 2>$null
git add app/Application/Auth/Handlers/DeleteUserHandler.php 2>$null
git commit -m "refactor(auth): update DeleteUserCommand for Dependency Injection"

git add app/Application/Auth/Commands/GenerateOtpCommand.php 2>$null
git add app/Application/Auth/Handlers/GenerateOtpHandler.php 2>$null
git add app/Application/Auth/Decorators/IdempotentGenerateOtpHandler.php 2>$null
git commit -m "refactor(auth): modernize GenerateOtp flow"

git add app/Application/Auth/Commands/VerifyOtpCommand.php 2>$null
git add app/Application/Auth/Handlers/VerifyOtpHandler.php 2>$null
git commit -m "refactor(auth): modernize VerifyOtp flow"

git add app/Application/Auth/Commands/UpdateUserPasswordCommand.php 2>$null
git add app/Application/Auth/Handlers/UpdateUserPasswordHandler.php 2>$null
git commit -m "refactor(auth): modernize UpdateUserPassword flow"

git add app/Application/Auth/Commands/LoginViaSocialProviderCommand.php 2>$null
git add app/Application/Auth/Handlers/LoginViaSocialProviderHandler.php 2>$null
git commit -m "refactor(auth): modernize LoginViaSocialProvider flow"

git add app/Application/Auth/Results/*.php 2>$null
git commit -m "feat(auth): add authentication result DTOs"

# ============================================================================
# CONTEXT 5: Identity Domain
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 5: Identity Domain" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Domain/Identity/Events/UserIdentityLinked.php 2>$null
git add app/Domain/Identity/Events/UserRegisteredViaSocial.php 2>$null
git add app/Domain/Identity/Events/SocialIdentityLinked.php 2>$null
git add app/Domain/Identity/Events/UserDeleted.php 2>$null
git commit -m "feat(identity): add identity domain events"

git add app/Domain/Identity/Exceptions/*.php 2>$null
git commit -m "feat(identity): add UserNotFoundException"

git add app/Domain/Identity/Entities/UserEntity.php 2>$null
git commit -m "refactor(identity): enrich UserEntity with new behavior"

git add app/Domain/Identity/Enums/UserRole.php 2>$null
git commit -m "refactor(identity): update UserRole enum backing types"

git add app/Domain/Identity/Repositories/UserRepositoryInterface.php 2>$null
git add app/Domain/Identity/Repositories/SocialIdentityRepositoryInterface.php 2>$null
git commit -m "refactor(identity): update repository interfaces for strict typing"

git add app/Application/Identity/Decorators/*.php 2>$null
git add app/Application/Identity/Handlers/*.php 2>$null
git commit -m "refactor(identity): update identity application layer"

# ============================================================================
# CONTEXT 6: Billing Domain
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 6: Billing Domain" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Domain/Billing/Entities/SubscriptionIntent.php 2>$null
git commit -m "feat(billing): add SubscriptionIntent entity"

git add app/Domain/Billing/Exceptions/*.php 2>$null
git commit -m "feat(billing): add PaymentEvidenceNotFoundException"

git add app/Domain/Billing/Entities/PaymentEntity.php 2>$null
git commit -m "refactor(billing): update PaymentEntity logic"

git add app/Domain/Billing/Events/PaymentReported.php 2>$null
git add app/Domain/Billing/Events/SubscriptionUpgraded.php 2>$null
git commit -m "refactor(billing): standardize Billing events"

git add app/Domain/Billing/Repositories/PaymentRepositoryInterface.php 2>$null
git add app/Domain/Billing/Services/DocumentStorageServiceInterface.php 2>$null
git commit -m "refactor(billing): update PaymentRepositoryInterface"

git add app/Application/Billing/Validators/ 2>$null
git add app/Application/Billing/Decorators/ 2>$null
git commit -m "feat(billing): add billing validators and decorators"

git add app/Application/Billing/Handlers/*.php 2>$null
git add app/Application/Billing/Commands/*.php 2>$null
git add app/Application/Billing/Results/*.php 2>$null
git add app/Application/Billing/Listeners/ 2>$null
git commit -m "feat(billing): update billing application layer"

# ============================================================================
# CONTEXT 7: Tenant Governance Domain
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 7: Tenant Governance" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Domain/TenantGovernance/Exceptions/*.php 2>$null
git commit -m "feat(tenant): add TenantNotFoundException"

git add app/Domain/TenantGovernance/Entities/TenantEntity.php 2>$null
git commit -m "refactor(tenant): update TenantEntity structure"

git add app/Domain/TenantGovernance/Repositories/TenantRepositoryInterface.php 2>$null
git commit -m "refactor(tenant): update TenantRepositoryInterface"

git add app/Application/Tenant/Decorators/*.php 2>$null
git commit -m "refactor(tenant): update IdempotentRegisterTenantHandler decorator"

# ============================================================================
# CONTEXT 8: Infrastructure - Outbox & Persistence
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 8: Outbox Infrastructure" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add config/outbox.php 2>$null
git commit -m "feat(outbox): add outbox configuration"

git add app/Domain/Common/Contracts/OutboxRepositoryInterface.php 2>$null
git add app/Domain/Shared/OutboxRepositoryInterface.php 2>$null
git commit -m "feat(outbox): add OutboxRepositoryInterface contract"

git add app/Infrastructure/Shared/Persistence/EloquentOutboxRepository.php 2>$null
git add app/Infrastructure/Models/OutboxMessage.php 2>$null
git add app/Infrastructure/Models/ 2>$null
git commit -m "feat(outbox): add EloquentOutboxRepository implementation"

git add app/Infrastructure/Persistence/OutboxLeaseManager.php 2>$null
git commit -m "feat(outbox): add OutboxLeaseManager for locking"

git add app/Infrastructure/Console/Commands/ProcessOutboxCommand.php 2>$null
git add app/Console/Commands/ProcessOutboxCommand.php 2>$null
git commit -m "feat(outbox): add ProcessOutboxCommand daemon"

git add app/Infrastructure/Console/Commands/PruneOutboxCommand.php 2>$null
git commit -m "feat(outbox): add PruneOutboxCommand maintenance job"

git add app/Infrastructure/Monitoring/OutboxMetricsCollector.php 2>$null
git add app/Infrastructure/Monitoring/PartitionHealthMonitor.php 2>$null
git commit -m "feat(outbox): add OutboxMetricsCollector"

git add app/Infrastructure/Persistence/AdaptiveSequenceGenerator.php 2>$null
git add app/Infrastructure/Persistence/AtomicSequenceGenerator.php 2>$null
git add app/Infrastructure/Persistence/BufferedSequenceGenerator.php 2>$null
git add app/Infrastructure/Persistence/QueryStrategist.php 2>$null
git add app/Infrastructure/Persistence/AtomicOwnershipTransfer.php 2>$null
git commit -m "feat(outbox): add adaptive sequence generators"

git add app/Infrastructure/Console/Commands/OutboxCoronerCommand.php 2>$null
git commit -m "feat(outbox): add OutboxCoronerCommand for dead letter handling"

# ============================================================================
# CONTEXT 9: Infrastructure - Idempotency
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 9: Idempotency Services" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Application/Common/Contracts/AtomicIdempotencyInterface.php 2>$null
git add app/Application/Common/Contracts/HashableCommand.php 2>$null
git add app/Application/Common/Decorators/ 2>$null
git add app/Application/Common/Exceptions/ 2>$null
git commit -m "feat(idempotency): add idempotency contracts and decorators"

git add app/Infrastructure/Common/Idempotency/AtomicIdempotencyService.php 2>$null
git add app/Infrastructure/Shared/Services/AtomicIdempotencyService.php 2>$null
git commit -m "feat(idempotency): add AtomicIdempotencyService implementation"

git add app/Infrastructure/Common/Idempotency/RedisIdempotencyService.php 2>$null
git commit -m "refactor(idempotency): optimize RedisIdempotencyService"

git add app/Infrastructure/Persistence/RedisIdempotencyStore.php 2>$null
git commit -m "feat(idempotency): add RedisIdempotencyStore adapter"

git add app/Infrastructure/Bus/Middleware/IdempotencyMiddleware.php 2>$null
git commit -m "feat(idempotency): add IdempotencyMiddleware for HTTP layer"

if (Test-Path "app/Infrastructure/Common/Idempotency/IdempotencyService.php") {
    git rm app/Infrastructure/Common/Idempotency/IdempotencyService.php 2>$null
    git commit -m "chore(idempotency): remove deprecated IdempotencyService"
}

# ============================================================================
# CONTEXT 10: Infrastructure - Repositories
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 10: Infrastructure Repositories" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Infrastructure/Auth/Repositories/EloquentOtpRepository.php 2>$null
git commit -m "refactor(infra): update EloquentOtpRepository"

git add app/Infrastructure/Identity/Persistence/EloquentUserRepository.php 2>$null
git add app/Infrastructure/Identity/RamseyUuidGenerator.php 2>$null
git commit -m "refactor(infra): update EloquentUserRepository"

git add app/Infrastructure/Persistence/EloquentPaymentRepository.php 2>$null
git add app/Infrastructure/Billing/Persistence/ 2>$null
git commit -m "feat(infra): add EloquentPaymentRepository"

git add app/Infrastructure/Repositories/TenantGovernance/EloquentTenantRepository.php 2>$null
git add app/Infrastructure/TenantGovernance/Persistence/EloquentTenantRepository.php 2>$null
git add app/Infrastructure/Persistence/EloquentTenantRepository.php 2>$null
git commit -m "refactor(infra): update EloquentTenantRepository"

git add app/Infrastructure/Persistence/EloquentSocialIdentityRepository.php 2>$null
git commit -m "refactor(infra): update EloquentSocialIdentityRepository"

git add app/Infrastructure/Persistence/Eloquent/Casts/TenantIdCast.php 2>$null
git commit -m "refactor(infra): update TenantIdCast"

if (Test-Path "app/Infrastructure/Repositories/Billing/EloquentPaymentRepository.php") {
    git rm app/Infrastructure/Repositories/Billing/EloquentPaymentRepository.php 2>$null
    git commit -m "chore(infra): remove deprecated repositories"
}

if (Test-Path "app/Infrastructure/Persistence/EloquentUserRepository.php") {
    git rm app/Infrastructure/Persistence/EloquentUserRepository.php 2>$null
    git commit -m "chore(infra): remove deprecated EloquentUserRepository"
}

# ============================================================================
# CONTEXT 11: Infrastructure - Services & Monitoring
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 11: Services & Monitoring" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Infrastructure/Security/ResilientRateLimiter.php 2>$null
git commit -m "feat(infra): add ResilientRateLimiter"

git add app/Infrastructure/Monitoring/ConsensusHealthGovernor.php 2>$null
git add app/Infrastructure/Monitoring/ElasticHealthProbe.php 2>$null
git add app/Infrastructure/Monitoring/ResilientHealthProbe.php 2>$null
git add app/Infrastructure/Monitoring/SystemHealthProbe.php 2>$null
git add app/Infrastructure/Monitoring/PersistentStateStore.php 2>$null
git commit -m "feat(infra): add health monitoring probes"

git add app/Infrastructure/Shared/Telemetry/ 2>$null
git add app/Infrastructure/Telemetry/ 2>$null
git commit -m "feat(infra): add telemetry services"

git add app/Infrastructure/Shared/Context/ 2>$null
git add app/Infrastructure/Shared/ConsoleContextProvider.php 2>$null
git add app/Infrastructure/Shared/RequestContextProvider.php 2>$null
git commit -m "feat(infra): add context providers"

git add app/Infrastructure/Shared/Time/ 2>$null
git commit -m "feat(infra): add time services"

git add app/Infrastructure/Shared/Services/ 2>$null
git commit -m "feat(infra): add shared services"

git add app/Infrastructure/Shared/Security/ 2>$null
git commit -m "feat(infra): add security services"

git add app/Infrastructure/Shared/Bus/ 2>$null
git commit -m "feat(infra): add bus services"

git add app/Infrastructure/Shared/Persistence/DatabaseTransactionManager.php 2>$null
git add app/Infrastructure/Shared/Persistence/Decorators/ 2>$null
git commit -m "feat(infra): add transaction manager"

git add app/Infrastructure/Bus/Middleware/TransactionalMiddleware.php 2>$null
git commit -m "refactor(infra): update TransactionalMiddleware"

git add app/Infrastructure/Bus/TransactionalEventDispatcher.php 2>$null
git commit -m "refactor(infra): update TransactionalEventDispatcher"

git add app/Infrastructure/Storage/LaravelDocumentStorageService.php 2>$null
git add app/Infrastructure/Storage/S3DocumentStorageService.php 2>$null
git commit -m "feat(infra): add document storage services"

git add app/Infrastructure/Shared/Services/LaravelPasswordHasher.php 2>$null
git add app/Domain/Shared/PasswordHasherInterface.php 2>$null
git commit -m "refactor(infra): update LaravelPasswordHasher"

git add app/Infrastructure/EventVersioning/ 2>$null
git commit -m "feat(infra): add event versioning infrastructure"

git add app/Infrastructure/Metrics/ 2>$null
git commit -m "feat(infra): add metrics infrastructure"

git add app/Infrastructure/Services/ 2>$null
git commit -m "feat(infra): add infrastructure services"

git add app/Infrastructure/Exceptions/ 2>$null
git commit -m "feat(infra): add infrastructure exceptions"

git add app/Infrastructure/Events/ 2>$null
git commit -m "feat(infra): add infrastructure events"

# ============================================================================
# CONTEXT 12: Database Migrations
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 12: Database Migrations" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add database/migrations/0000_01_01_000001_create_tenants_table.php 2>$null
git commit -m "feat(db): add tenants table migration"

git add database/migrations/2026_01_19_140000_create_outbox_messages_table.php 2>$null
git commit -m "feat(db): add outbox messages migration"

git add database/migrations/2026_01_20_000000_create_subscription_intents_table.php 2>$null
git commit -m "feat(db): add subscription intents migration"

git add database/migrations/2026_01_20_000001_add_version_to_tenants_table.php 2>$null
git add database/migrations/2026_01_20_000004_add_version_to_payments_table.php 2>$null
git commit -m "feat(db): add versioning columns migrations"

git add database/migrations/2026_01_20_000002_add_client_key_to_subscription_intents.php 2>$null
git add database/migrations/2026_01_20_000003_add_idempotency_key_to_payments_table.php 2>$null
git add database/migrations/2026_01_20_000005_add_index_to_proof_path.php 2>$null
git commit -m "feat(db): add billing indexes migrations"

git add database/migrations/2026_01_20_000006_harden_outbox_messages.php 2>$null
git add database/migrations/2026_01_20_000007_add_locked_at_to_outbox.php 2>$null
git add database/migrations/2026_01_20_000008_add_partition_to_outbox.php 2>$null
git add database/migrations/2026_01_20_000009_create_outbox_sequences_table.php 2>$null
git add database/migrations/2026_01_20_000009_optimize_outbox_indexes.php 2>$null
git add database/migrations/2026_01_20_000010_create_outbox_shards_table.php 2>$null
git add database/migrations/2026_01_20_000011_add_worker_id_to_outbox_messages.php 2>$null
git commit -m "feat(db): add outbox hardening migrations"

git add database/migrations/2026_01_18_053355_create_domain_outbox_table.php 2>$null
git add database/migrations/2026_01_18_053356_create_processed_commands_table.php 2>$null
git commit -m "feat(db): add domain outbox table migration"

git add database/migrations/2026_01_11_233000_create_one_time_passwords_table.php 2>$null
git commit -m "refactor(db): update OTP table migration"

# Remove deprecated migrations
$deprecatedMigrations = @(
    "database/migrations/2025_01_01_000003_create_tenants_table.php",
    "database/migrations/2026_01_08_170000_create_domain_outbox_messages_table.php",
    "database/migrations/2026_01_08_212500_create_domain_outbox_events_table.php",
    "database/migrations/2026_01_13_183000_create_outbox_messages_table.php"
)
foreach ($m in $deprecatedMigrations) {
    if (Test-Path $m) { git rm $m 2>$null }
}
git commit -m "chore(db): remove deprecated migrations"

# Update existing migrations
git add database/migrations/0001_01_01_000000_create_users_table.php 2>$null
git add database/migrations/2025_01_01_*.php 2>$null
git add database/migrations/2025_01_02_*.php 2>$null
git add database/migrations/2025_01_03_*.php 2>$null
git add database/migrations/2025_12_*.php 2>$null
git add database/migrations/2026_01_04_*.php 2>$null
git add database/migrations/2026_01_05_*.php 2>$null
git commit -m "refactor(db): update existing migrations for tenant compliance"

git add database/factories/*.php 2>$null
git commit -m "refactor(db): update factories"

# ============================================================================
# CONTEXT 13: Configuration & Providers
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 13: Configuration & Providers" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Providers/AppServiceProvider.php 2>$null
git commit -m "refactor(config): register new services in AppServiceProvider"

git add app/Providers/EventServiceProvider.php 2>$null
git commit -m "refactor(config): update EventServiceProvider bindings"

git add app/Providers/InfrastructureServiceProvider.php 2>$null
git commit -m "refactor(config): register InfrastructureServiceProvider"

git add bootstrap/app.php 2>$null
git commit -m "refactor(config): update bootstrap/app.php"

git add config/auth.php 2>$null
git commit -m "refactor(config): harden auth configuration"

git add routes/api.php 2>$null
git add routes/console.php 2>$null
git commit -m "refactor(routes): update routes"

git add phpunit.xml 2>$null
git commit -m "refactor(config): update phpunit configuration"

git add composer.json 2>$null
git add composer.lock 2>$null
git commit -m "chore(deps): update composer dependencies"

# ============================================================================
# CONTEXT 14: Tests
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 14: Tests" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add tests/Architecture/AuthHandlerSafetyTest.php 2>$null
git add tests/Architecture/Contracts/ 2>$null
git add tests/Architecture/Security/ 2>$null
git add tests/Architecture/Stress/ 2>$null
git add tests/Architecture/Scalability/ 2>$null
git commit -m "test(arch): add architecture tests"

git add tests/Feature/Auth/AtomicIdempotencyIntegrationTest.php 2>$null
git add tests/Feature/Auth/TelemetryIntegrationTest.php 2>$null
git commit -m "test(auth): add AtomicIdempotency integration tests"

git add tests/Feature/Infrastructure/Scale10xTest.php 2>$null
git commit -m "test(scale): add Scale10xTest"

git add tests/Integration/ 2>$null
git add tests/Infrastructure/ 2>$null
git commit -m "test(integration): add integration tests"

git add tests/Debug/ 2>$null
git commit -m "test(debug): add debug tests"

git add tests/Contract/Identity/RegisterUserResultTest.php 2>$null
git add tests/Feature/Auth/PasswordUpdateTest.php 2>$null
git add tests/Feature/Identity/Auth/RegistrationToLoginFlowTest.php 2>$null
git add tests/Unit/Exports/SalesExportUnitTest.php 2>$null
git commit -m "refactor(test): update existing tests to match new contracts"

# ============================================================================
# CONTEXT 15: Console Commands, Middleware & Models
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 15: Console & HTTP Middleware" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Infrastructure/Console/Commands/CheckPaymentStatusCommand.php 2>$null
git add app/Infrastructure/Console/Commands/ForcePromotePaymentCommand.php 2>$null
git add app/Infrastructure/Console/Commands/PruneStaleFinancialDataCommand.php 2>$null
git add app/Infrastructure/Console/Commands/ReconcileSubscriptionIntentsCommand.php 2>$null
git add app/Infrastructure/Console/Commands/RescueStuckProofsCommand.php 2>$null
git commit -m "feat(console): add billing console commands"

git add app/Console/Commands/PruneIdempotencyCommand.php 2>$null
git commit -m "feat(console): add PruneIdempotencyCommand"

git add app/Console/Commands/AttackOtpCommand.php 2>$null
git commit -m "feat(console): add AttackOtpCommand for stress testing"

git add app/Http/Middleware/EnsureIdempotencyKey.php 2>$null
git add app/Http/Middleware/SmartIdempotencyMiddleware.php 2>$null
git commit -m "feat(http): add idempotency middlewares"

git add app/Models/Payment.php 2>$null
git add app/Models/Tenant.php 2>$null
git add app/Models/User.php 2>$null
git commit -m "refactor(models): update Eloquent models"

git add app/Exports/SalesExport.php 2>$null
git commit -m "refactor(exports): update SalesExport"

git add app/Actions/Fortify/UpdateUserPassword.php 2>$null
git commit -m "refactor(actions): update Fortify actions"

# ============================================================================
# CONTEXT 16: Remaining New Directories & Files
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "CONTEXT 16: Remaining Files" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

git add app/Application/Shared/Contracts/*.php 2>$null
git commit -m "feat(shared): add shared application contracts"

git add app/Domain/User/ 2>$null
git commit -m "feat(user): add User domain"

git add app/Domain/Identity/Entities/Tenant.php 2>$null
git commit -m "feat(identity): add Tenant entity"

git add resources/schemas/ 2>$null
git commit -m "feat(schemas): add JSON schemas for validation"

git add proto/ 2>$null
git commit -m "feat(proto): add protocol definitions"

git add docker-compose.testing.yml 2>$null
git commit -m "chore(docker): add testing docker-compose"

# Remove strange file if exists
if (Test-Path "ource pages -m - Add CRUD pages for announcement management") {
    git rm "ource pages -m - Add CRUD pages for announcement management" 2>$null
    git commit -m "chore: remove erroneous file"
}

# ============================================================================
# FINAL CLEANUP - Remove any remaining deleted files
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "FINAL: Cleanup Deleted Files" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

if (Test-Path "app/Application/Shared/Contracts/SocialIdentityRepositoryInterface.php") {
    git rm "app/Application/Shared/Contracts/SocialIdentityRepositoryInterface.php" 2>$null
}
if (Test-Path "app/Domain/Shared/Contracts/TransactionManagerInterface.php") {
    git rm "app/Domain/Shared/Contracts/TransactionManagerInterface.php" 2>$null
}
git add -u 2>$null
git commit -m "chore: remove remaining deprecated files"

# ============================================================================
# COMPLETION
# ============================================================================
Write-Host "`n========================================" -ForegroundColor Green
Write-Host "ATOMIC COMMITS COMPLETE" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host "`nRun 'git log --oneline -50' to verify commits." -ForegroundColor Yellow
Write-Host "Run 'git status' to check for uncommitted files.`n" -ForegroundColor Yellow
