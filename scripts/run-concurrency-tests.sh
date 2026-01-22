#!/bin/bash
# ╔══════════════════════════════════════════════════════════════════════════╗
# ║  CONCURRENCY TEST EXECUTION SCRIPT (Bash/Linux/CI)                       ║
# ║  Usage: ./scripts/run-concurrency-tests.sh                               ║
# ║  Purpose: Spin up MySQL, run SKIP LOCKED tests, tear down                ║
# ╚══════════════════════════════════════════════════════════════════════════╝

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
TEST_FILTER="${1:-OutboxScalingTest}"
KEEP_CONTAINER="${KEEP_CONTAINER:-false}"

echo "═══════════════════════════════════════════════════════════════"
echo "  CONCURRENCY TEST SUITE - MySQL 8.0 Ephemeral Environment"
echo "═══════════════════════════════════════════════════════════════"

cd "$PROJECT_ROOT"

# ─────────────────────────────────────────────────────────────────────────────
# PHASE 1: SPIN UP MYSQL CONTAINER
# ─────────────────────────────────────────────────────────────────────────────
echo -e "\n[1/5] Starting MySQL 8.0 container..."
docker compose -f docker-compose.testing.yml up -d

# ─────────────────────────────────────────────────────────────────────────────
# PHASE 2: WAIT FOR HEALTHY STATUS
# ─────────────────────────────────────────────────────────────────────────────
echo "[2/5] Waiting for MySQL to be healthy..."

MAX_ATTEMPTS=30
ATTEMPT=0

until [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; do
    HEALTH=$(docker inspect --format='{{.State.Health.Status}}' conecta_mysql_testing 2>/dev/null || echo "starting")
    
    if [ "$HEALTH" == "healthy" ]; then
        echo "  ✓ MySQL is healthy!"
        break
    fi
    
    ATTEMPT=$((ATTEMPT + 1))
    echo "  Attempt $ATTEMPT/$MAX_ATTEMPTS - Status: $HEALTH"
    sleep 2
done

if [ "$HEALTH" != "healthy" ]; then
    echo "ERROR: MySQL failed to become healthy after $MAX_ATTEMPTS attempts"
    docker compose -f docker-compose.testing.yml down -v
    exit 1
fi

# ─────────────────────────────────────────────────────────────────────────────
# PHASE 3: RUN MIGRATIONS
# ─────────────────────────────────────────────────────────────────────────────
echo "[3/5] Running migrations on testing database..."

export DB_CONNECTION=mysql
export DB_HOST=127.0.0.1
export DB_PORT=33060
export DB_DATABASE=testing_concurrency
export DB_USERNAME=root
export DB_PASSWORD=secret

php artisan migrate:fresh --force --seed

echo "  ✓ Migrations complete!"

# ─────────────────────────────────────────────────────────────────────────────
# PHASE 4: EXECUTE CONCURRENCY TESTS
# ─────────────────────────────────────────────────────────────────────────────
echo "[4/5] Running concurrency tests..."
echo "  Filter: $TEST_FILTER"

TEST_RESULT=0
php artisan test --filter="$TEST_FILTER" || TEST_RESULT=$?

if [ $TEST_RESULT -eq 0 ]; then
    echo -e "\n  ✓ ALL TESTS PASSED!"
else
    echo -e "\n  ✗ TESTS FAILED"
fi

# ─────────────────────────────────────────────────────────────────────────────
# PHASE 5: CLEANUP
# ─────────────────────────────────────────────────────────────────────────────
if [ "$KEEP_CONTAINER" != "true" ]; then
    echo "[5/5] Destroying ephemeral container..."
    docker compose -f docker-compose.testing.yml down -v
    echo "  ✓ Container destroyed"
else
    echo "[5/5] Keeping container (set KEEP_CONTAINER=true was used)"
fi

# Clear environment
unset DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD

echo -e "\n═══════════════════════════════════════════════════════════════"
echo "  TEST EXECUTION COMPLETE"
echo "═══════════════════════════════════════════════════════════════"

exit $TEST_RESULT
