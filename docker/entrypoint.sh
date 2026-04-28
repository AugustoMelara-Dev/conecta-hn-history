#!/usr/bin/env sh
set -e

cd /app

case "${APP_ENV:-local}" in
  production|staging)
    if [ "${RUN_PRODUCTION_GUARD:-true}" = "true" ]; then
      if ! php artisan config:production-guard --env="${APP_ENV}" --json; then
        if [ "${ENFORCE_PRODUCTION_GUARD:-false}" = "true" ]; then
          exit 1
        fi

        echo "Production guard reported violations; continuing because ENFORCE_PRODUCTION_GUARD is not true." >&2
      fi
    fi
    ;;
esac

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --no-interaction
fi

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
