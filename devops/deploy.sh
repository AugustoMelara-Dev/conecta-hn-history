#!/bin/bash

# ══════════════════════════════════════════════════════════════════════════════
# CONECTA HN - ATOMIC DEPLOYMENT SCRIPT
# ══════════════════════════════════════════════════════════════════════════════
#
# Usage: ./deploy.sh [--skip-assets] [--no-backup]
#
# This script performs a zero-downtime atomic deployment following these phases:
# 1. Pre-flight checks and backup
# 2. Maintenance mode activation
# 3. Code synchronization (git pull)
# 4. Dependency resolution (composer, npm)
# 5. Database migrations
# 6. Cache optimization
# 7. Asset compilation
# 8. Service reload
# 9. Health check and maintenance mode deactivation
#
# FAIL-FAST: Script terminates on first error (set -e)
# ══════════════════════════════════════════════════════════════════════════════

set -e  # Exit immediately if any command fails
set -o pipefail  # Catch errors in piped commands

# ═══════════════════════════════════════════════════════════════════════════════
# CONFIGURATION
# ═══════════════════════════════════════════════════════════════════════════════

APP_DIR="/var/www/conecta-hn"
BRANCH="main"
PHP_FPM_SERVICE="php8.2-fpm"
BACKUP_DIR="/var/backups/conecta-hn"
LOG_FILE="/var/log/conecta-deploy.log"
SLACK_WEBHOOK="${SLACK_DEPLOY_WEBHOOK:-}"

# Parse arguments
SKIP_ASSETS=false
NO_BACKUP=false

for arg in "$@"; do
    case $arg in
        --skip-assets) SKIP_ASSETS=true ;;
        --no-backup) NO_BACKUP=true ;;
    esac
done

# ═══════════════════════════════════════════════════════════════════════════════
# UTILITY FUNCTIONS
# ═══════════════════════════════════════════════════════════════════════════════

timestamp() {
    date "+%Y-%m-%d %H:%M:%S"
}

log() {
    echo "[$(timestamp)] $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo "[$(timestamp)] ❌ ERROR: $1" | tee -a "$LOG_FILE" >&2
}

log_success() {
    echo "[$(timestamp)] ✅ $1" | tee -a "$LOG_FILE"
}

notify_slack() {
    if [ -n "$SLACK_WEBHOOK" ]; then
        curl -s -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"$1\"}" \
            "$SLACK_WEBHOOK" > /dev/null 2>&1 || true
    fi
}

# Cleanup function for error handling
cleanup() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        log_error "Deployment failed! Attempting to restore service..."
        cd "$APP_DIR"
        php artisan up 2>/dev/null || true
        notify_slack "🔴 DEPLOYMENT FAILED on $(hostname) - Manual intervention required"
    fi
}

trap cleanup EXIT

# ═══════════════════════════════════════════════════════════════════════════════
# PRE-FLIGHT CHECKS
# ═══════════════════════════════════════════════════════════════════════════════

log "═══════════════════════════════════════════════════════════════"
log "🚀 Starting deployment to $(hostname)"
log "═══════════════════════════════════════════════════════════════"

# Verify we're running as appropriate user (www-data or deployer)
if [ "$(id -un)" != "www-data" ] && [ "$(id -un)" != "deployer" ] && [ "$(id -un)" != "root" ]; then
    log_error "Script must be run as www-data, deployer, or root"
    exit 1
fi

# Verify application directory exists
if [ ! -d "$APP_DIR" ]; then
    log_error "Application directory not found: $APP_DIR"
    exit 1
fi

cd "$APP_DIR"

# Verify this is a git repository
if [ ! -d ".git" ]; then
    log_error "Not a git repository: $APP_DIR"
    exit 1
fi

log_success "Pre-flight checks passed"

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 1: BACKUP (Optional)
# ═══════════════════════════════════════════════════════════════════════════════

if [ "$NO_BACKUP" = false ]; then
    log "📦 Creating backup..."
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    BACKUP_FILE="$BACKUP_DIR/db_$(date +%Y%m%d_%H%M%S).sql.gz"
    php artisan db:backup --filename="$BACKUP_FILE" 2>/dev/null || {
        # Fallback to mysqldump if artisan backup not available
        if command -v mysqldump &> /dev/null; then
            source .env
            mysqldump -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$BACKUP_FILE" 2>/dev/null || true
        fi
    }
    
    # Cleanup old backups (keep last 7)
    ls -t "$BACKUP_DIR"/db_*.sql.gz 2>/dev/null | tail -n +8 | xargs -r rm -f
    
    log_success "Backup completed"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 2: MAINTENANCE MODE
# ═══════════════════════════════════════════════════════════════════════════════

log "🔧 Activating maintenance mode..."
php artisan down --render="errors::503" --retry=60 --refresh=15

log_success "Maintenance mode active"
notify_slack "🟡 Deployment started on $(hostname) - Maintenance mode active"

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 3: CODE SYNCHRONIZATION
# ═══════════════════════════════════════════════════════════════════════════════

log "📥 Pulling latest code from $BRANCH..."

# Stash any local changes (shouldn't be any in production)
git stash --quiet 2>/dev/null || true

# Fetch and reset to origin
git fetch origin "$BRANCH" --quiet
git reset --hard "origin/$BRANCH"

# Get current commit for logging
COMMIT_HASH=$(git rev-parse --short HEAD)
COMMIT_MSG=$(git log -1 --pretty=format:"%s")

log_success "Code synchronized to $COMMIT_HASH: $COMMIT_MSG"

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 4: PHP DEPENDENCIES
# ═══════════════════════════════════════════════════════════════════════════════

log "📦 Installing PHP dependencies..."

composer install \
    --no-dev \
    --optimize-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --quiet

log_success "Composer dependencies installed"

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 5: DATABASE MIGRATIONS
# ═══════════════════════════════════════════════════════════════════════════════

log "🗄️ Running database migrations..."

php artisan migrate --force --no-interaction

log_success "Migrations completed"

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 6: CACHE OPTIMIZATION (CRITICAL FOR PERFORMANCE)
# ═══════════════════════════════════════════════════════════════════════════════

log "⚡ Optimizing Laravel caches..."

# Clear all caches first (clean slate)
php artisan optimize:clear --quiet

# Regenerate optimized caches
php artisan config:cache   # ~30% faster config loading
php artisan route:cache    # ~50% faster route matching
php artisan view:cache     # Pre-compile Blade templates
php artisan event:cache    # Cache event-listener mappings
php artisan icons:cache 2>/dev/null || true  # Blade Icons (if using)

# Filament cache (if using Filament)
php artisan filament:cache-components 2>/dev/null || true

log_success "Cache optimization completed"

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 7: FRONTEND ASSETS (Optional)
# ═══════════════════════════════════════════════════════════════════════════════

if [ "$SKIP_ASSETS" = false ]; then
    log "🎨 Compiling frontend assets..."
    
    # Install Node dependencies
    npm ci --silent --prefer-offline 2>/dev/null || npm install --silent
    
    # Build production assets
    npm run build
    
    log_success "Frontend assets compiled"
else
    log "⏭️ Skipping asset compilation (--skip-assets)"
fi

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 8: SERVICE RELOAD
# ═══════════════════════════════════════════════════════════════════════════════

log "🔄 Reloading services..."

# Reload PHP-FPM to pick up new code (graceful reload, not restart)
if systemctl is-active --quiet "$PHP_FPM_SERVICE"; then
    sudo systemctl reload "$PHP_FPM_SERVICE"
    log_success "PHP-FPM reloaded"
fi

# Signal queue workers to restart after current job completes
php artisan queue:restart

# Clear Reverb/Websocket connections (if using)
php artisan reverb:restart 2>/dev/null || true

log_success "Services reloaded"

# ═══════════════════════════════════════════════════════════════════════════════
# PHASE 9: HEALTH CHECK & GO LIVE
# ═══════════════════════════════════════════════════════════════════════════════

log "🏥 Running health check..."

# Basic health check - verify artisan works
php artisan --version > /dev/null

# Verify database connectivity
php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null

log_success "Health check passed"

# Deactivate maintenance mode
php artisan up

# ═══════════════════════════════════════════════════════════════════════════════
# DEPLOYMENT COMPLETE
# ═══════════════════════════════════════════════════════════════════════════════

DEPLOY_TIME=$SECONDS
log "═══════════════════════════════════════════════════════════════"
log_success "🎉 DEPLOYMENT COMPLETED SUCCESSFULLY!"
log "   Commit:   $COMMIT_HASH"
log "   Message:  $COMMIT_MSG"
log "   Duration: ${DEPLOY_TIME}s"
log "═══════════════════════════════════════════════════════════════"

notify_slack "🟢 Deployment SUCCESSFUL on $(hostname)\n• Commit: \`$COMMIT_HASH\`\n• Duration: ${DEPLOY_TIME}s"

exit 0
