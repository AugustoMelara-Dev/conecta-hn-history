#!/bin/bash
# ╔══════════════════════════════════════════════════════════════════════════╗
# ║  ATOMIC COMMIT SEQUENCE - Hexagonal Architecture Remediation            ║
# ╚══════════════════════════════════════════════════════════════════════════╝
#
# USAGE: bash scripts/git-atomic-commits.sh
# PREREQUISITE: Run `php artisan test --filter=HexagonalArchitectureTest` first

set -e  # Exit on error

echo "🔄 Creating feature branch..."
git checkout -b feature/refactor-auth-hexagonal-standard 2>/dev/null || git checkout feature/refactor-auth-hexagonal-standard

echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "COMMIT 1: Shared Infrastructure (Ports & Adapters)"
echo "═══════════════════════════════════════════════════════════════════════════════"
git add app/Application/Shared/Contracts/LoggerInterface.php
git add app/Application/Shared/Contracts/TransactionManagerInterface.php
git add app/Infrastructure/Logging/LaravelLoggerAdapter.php
git add app/Infrastructure/Persistence/LaravelTransactionManager.php
git commit -m "feat(arch): add ports and adapters for framework-agnostic logging and transactions

- Create LoggerInterface for Application layer isolation
- Create LaravelLoggerAdapter implementing LoggerInterface
- Implement TransactionManagerInterface for DB transaction abstraction
- Ensure zero direct Illuminate facade usage in Application layer"

echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "COMMIT 2: Identity Migration (UUID Strings)"
echo "═══════════════════════════════════════════════════════════════════════════════"
git add app/ValueObjects/TenantId.php
git commit -m "refactor(domain): migrate identity strategy to UUID strings and deprecate toInt()

- Add toString() method as primary identity serialization
- Mark toInt() as @deprecated with trigger_error
- Prepare for UUID v7 migration in async flows"

echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "COMMIT 3: Domain Events Remediation"
echo "═══════════════════════════════════════════════════════════════════════════════"
git add app/Events/
git add app/Domain/*/Events/
git commit -m "refactor(events): migrate all Domain Events from toInt() to toString()

- Update toPrimitives() to serialize tenant_id as string
- Update fromPrimitives() for string-based deserialization
- Ensure queue-safe serialization for all 15+ events"

echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "COMMIT 4: Application Purification"
echo "═══════════════════════════════════════════════════════════════════════════════"
git add app/Application/
git commit -m "refactor(application): decouple handlers and middleware from Laravel facades

- Remove DB facade from TransactionMiddleware
- Remove Log facade from EnhanceTenantProfileHandler
- Remove Log facade from RecordInteractionHandler
- Inject TransactionManagerInterface and LoggerInterface"

echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "COMMIT 5: Async Safety (Listeners)"
echo "═══════════════════════════════════════════════════════════════════════════════"
git add app/Listeners/
git commit -m "refactor(listeners): secure async processing with entity rehydration and string IDs

- Replace toInt() with toString() in all listeners
- Migrate TenantQueueMiddleware to use string tenant context
- Ensure 100% UUID string compatibility in async flows"

echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "COMMIT 6: Architecture Guardian (Tests)"
echo "═══════════════════════════════════════════════════════════════════════════════"
git add tests/Arch/HexagonalArchitectureTest.php
git add tests/Arch/ArchitectureTest.php
git commit -m "test(arch): add automated enforcement of hexagonal boundaries and identity types

- Add Application Layer Isolation Guard (no DB/Log facades)
- Add UUID Identity Enforcement Guard (no toInt() in async)
- Add Infrastructure Boundary Guard
- Add Ports & Adapters existence verification"

echo ""
echo "✅ ALL COMMITS COMPLETED SUCCESSFULLY!"
echo ""
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "NEXT STEPS:"
echo "═══════════════════════════════════════════════════════════════════════════════"
echo "1. Run: php artisan test --filter=HexagonalArchitectureTest"
echo "2. Run: php artisan test --filter=ArchitectureTest"
echo "3. Push: git push -u origin feature/refactor-auth-hexagonal-standard"
echo "4. Create Pull Request for code review"
